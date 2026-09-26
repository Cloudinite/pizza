<?php
defined('PS_APP') || exit;

const PS_DAYS_SHORT = [1 => 'Po', 2 => 'Ut', 3 => 'St', 4 => 'Št', 5 => 'Pi', 6 => 'So', 7 => 'Ne'];
const PS_DAYS_ACC = [1 => 'v pondelok', 2 => 'v utorok', 3 => 'v stredu', 4 => 'vo štvrtok', 5 => 'v piatok', 6 => 'v sobotu', 7 => 'v nedeľu'];

/** @return array<int, array{0:string,1:string}|null> ISO weekday → [open, close] or null (closed) */
function week_hours(): array
{
    $raw = json_decode(setting('hours'), true);
    $out = [];
    foreach (array_keys(PS_DAYS) as $d) {
        $h = $raw[$d] ?? $raw[(string) $d] ?? null;
        $valid = is_array($h) && count($h) === 2
            && preg_match('/^\d{2}:\d{2}$/', (string) $h[0]) && preg_match('/^\d{2}:\d{2}$/', (string) $h[1])
            && $h[0] < $h[1];
        $out[$d] = $valid ? [(string) $h[0], (string) $h[1]] : null;
    }
    return $out;
}

function ps_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now');
}

/** @return array{0:DateTimeImmutable,1:DateTimeImmutable}|null */
function day_window(DateTimeImmutable $day): ?array
{
    $h = week_hours()[(int) $day->format('N')] ?? null;
    if ($h === null) {
        return null;
    }
    $date = $day->format('Y-m-d');
    return [new DateTimeImmutable("$date {$h[0]}"), new DateTimeImmutable("$date {$h[1]}")];
}

/** Human text like "dnes o 10:00", "zajtra o 10:00", "v pondelok o 10:00". */
function next_opening_text(?DateTimeImmutable $now = null): string
{
    $now ??= ps_now();
    for ($i = 0; $i <= 7; $i++) {
        $day = $now->setTime(0, 0)->modify("+$i day");
        $w = day_window($day);
        if ($w === null || ($i === 0 && $now >= $w[0])) {
            continue;
        }
        $time = $w[0]->format('G:i');
        return match ($i) {
            0 => "dnes o $time",
            1 => "zajtra o $time",
            default => PS_DAYS_ACC[(int) $day->format('N')] . " o $time",
        };
    }
    return 'čoskoro';
}

/** @return array{open:bool, text:string} */
function open_state(?DateTimeImmutable $now = null): array
{
    $now ??= ps_now();
    $w = day_window($now);
    if ($w !== null && $now >= $w[0] && $now < $w[1]) {
        return ['open' => true, 'text' => 'Otvorené · do ' . $w[1]->format('G:i')];
    }
    return ['open' => false, 'text' => 'Zatvorené · otvárame ' . next_opening_text($now)];
}

/**
 * What the checkout can offer right now.
 * @return array{ok:bool, asap:bool, slots:string[], message:string}
 */
function ordering_state(?DateTimeImmutable $now = null): array
{
    $now ??= ps_now();
    $closed = static fn (string $msg) => ['ok' => false, 'asap' => false, 'slots' => [], 'message' => $msg];

    if (!setting_bool('ordering_enabled')) {
        $phone = setting('phone');
        return $closed('Online objednávky sú momentálne pozastavené.' . ($phone !== '' ? " Zavolajte nám na $phone." : ''));
    }
    $w = day_window($now);
    if ($w === null) {
        return $closed('Dnes máme zatvorené. Otvárame ' . next_opening_text($now) . '.');
    }
    [$open, $close] = $w;
    $lastOrder = $close->modify('-' . max(0, setting_int('last_order_minutes')) . ' minutes');
    if ($now >= $lastOrder) {
        return $closed('Online objednávky na dnes sú už uzavreté. Otvárame ' . next_opening_text($now) . '.');
    }

    $earliest = ($now > $open ? $now : $open)->modify('+' . max(0, setting_int('prep_minutes')) . ' minutes');
    $ts = $earliest->getTimestamp();
    $slot = $earliest->setTimestamp((int) (ceil($ts / 900) * 900));
    $slots = [];
    while ($slot <= $close && count($slots) < 60) {
        $slots[] = $slot->format('H:i');
        $slot = $slot->modify('+15 minutes');
    }

    $asap = $now >= $open;
    if (!$asap && !$slots) {
        return $closed('Online objednávky na dnes sú už uzavreté.');
    }
    $message = $asap ? '' : 'Otvárame o ' . $open->format('G:i') . ' – objednávku si môžete naplánovať vopred.';
    return ['ok' => true, 'asap' => $asap, 'slots' => $slots, 'message' => $message];
}

/** Groups consecutive days with equal hours: [["Po – Ne", "10:00 – 21:00"]]. */
function hours_rows(): array
{
    $rows = [];
    $week = week_hours();
    $start = 1;
    for ($d = 1; $d <= 7; $d++) {
        $next = $week[$d + 1] ?? 'end';
        if ($d === 7 || $next !== $week[$d]) {
            $label = $start === $d ? PS_DAYS_SHORT[$d] : PS_DAYS_SHORT[$start] . ' – ' . PS_DAYS_SHORT[$d];
            $h = $week[$d];
            $rows[] = [$label, $h ? $h[0] . ' – ' . $h[1] : 'Zatvorené'];
            $start = $d + 1;
        }
    }
    return $rows;
}

/** "10:00" → "10", "09:30" → "9:30" */
function short_time(string $hm): string
{
    [$h, $m] = explode(':', $hm) + [1 => '00'];
    return ((int) $h) . ($m === '00' ? '' : ':' . $m);
}

function opening_hours_schema(): array
{
    $map = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
    $out = [];
    foreach (week_hours() as $d => $h) {
        if ($h) {
            $out[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $map[$d], 'opens' => $h[0], 'closes' => $h[1]];
        }
    }
    return $out;
}
