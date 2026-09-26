<?php
defined('PS_APP') || exit;

const PS_DAYS = [1 => 'Pondelok', 2 => 'Utorok', 3 => 'Streda', 4 => 'Štvrtok', 5 => 'Piatok', 6 => 'Sobota', 7 => 'Nedeľa'];

function setting_defaults(): array
{
    $hours = [];
    foreach (array_keys(PS_DAYS) as $d) {
        $hours[$d] = ['10:00', '21:00'];
    }
    return [
        'site_name'          => 'Pizza Slice Pezinok',
        'tagline'            => 'Pizza na kúsky – vždy čerstvá, každý deň',
        'phone'              => '',
        'email'              => '',
        'street'             => '',
        'city'               => 'Pezinok',
        'zip'                => '902 01',
        'maps_url'           => '',
        'facebook_url'       => 'https://www.facebook.com/pizzaslicepk',
        'instagram_url'      => 'https://www.instagram.com/pizzaslicepk/',
        'company_name'       => '',
        'company_ico'        => '',
        'company_address'    => '',
        'announcement'       => '',
        'ordering_enabled'   => '1',
        'hours'              => json_encode($hours),
        'prep_minutes'       => '15',
        'last_order_minutes' => '15',
        'delivery_enabled'   => '1',
        'delivery_fee_cents' => '150',
        'delivery_min_cents' => '1000',
        'delivery_area'      => 'Pezinok',
        'retention_days'     => '90',
        'schema_version'     => '1',
    ];
}

function settings(bool $reload = false): array
{
    static $cache = null;
    if ($cache === null || $reload) {
        $cache = setting_defaults();
        foreach (db()->query('SELECT k, v FROM settings') as $row) {
            if (array_key_exists($row['k'], $cache)) {
                $cache[$row['k']] = (string) $row['v'];
            }
        }
    }
    return $cache;
}

function setting(string $key): string
{
    return settings()[$key] ?? '';
}

function setting_int(string $key): int
{
    return (int) setting($key);
}

function setting_bool(string $key): bool
{
    return setting($key) === '1';
}

function save_settings(array $values): void
{
    $allowed = setting_defaults();
    $st = db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
    foreach ($values as $k => $v) {
        if (array_key_exists($k, $allowed)) {
            $st->execute([$k, (string) $v]);
        }
    }
    settings(true);
}

/** "vyzdvihni si ju bez čakania" / "… alebo si ju nechaj doniesť" depending on the delivery setting. */
function fulfilment_phrase(bool $formal = false): string
{
    if (setting_bool('delivery_enabled')) {
        return $formal ? 'vyzdvihnite si ju alebo si ju nechajte doniesť' : 'vyzdvihni si ju alebo si ju nechaj doniesť';
    }
    return $formal ? 'vyzdvihnite si ju bez čakania' : 'vyzdvihni si ju bez čakania';
}

/** Public-facing single-line address, e.g. "Hlavná 1, 902 01 Pezinok". */
function address_line(): string
{
    $parts = array_filter([setting('street'), trim(setting('zip') . ' ' . setting('city'))]);
    return implode(', ', $parts);
}

function phone_href(string $phone): string
{
    return 'tel:' . preg_replace('/[^0-9+]/', '', $phone);
}
