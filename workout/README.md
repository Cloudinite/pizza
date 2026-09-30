# LiftLog – workout tracker

A small, fast workout log for iPhone and Android. It installs to the home screen, works fully offline and keeps your data on the phone. Plain HTML/CSS/JS: no frameworks, no build step, no server code.

| | |
|---|---|
| **Workout** (daily) | Today's workout from your split, with a week strip to jump to any day. Each exercise has sets with **− / +** steppers for weight and reps. You can also type the numbers. Tap ✓ to finish a set; it starts a rest timer. Weights carry over from your **last session**, and changing set 1's weight updates the sets below it. |
| **Week** | Mon–Sun overview: what's planned, what's done (✓ or % done), missed days, and weekly sets and volume. |
| **Splits** | Start from a template (Push/Pull/Legs, Upper/Lower, Full Body, body-part split) or from scratch. Rename days, pick their weekdays, and add, edit or reorder exercises (sets × reps × starting weight). |
| **Progress** | Every logged workout by month, plus each exercise's best set, estimated 1RM, PRs and full history. |
| **Settings** (⚙) | kg / lb, weight step, rest timer length (or off), week start, light/dark/auto, backup export/import. |

## Put it on your phone

The app needs to be served over **HTTPS** once so the phone can install it. After that it runs offline.

Any static host works. Upload the contents of this `workout/` folder, for example:
- **Netlify Drop**: drag the `workout` folder onto <https://app.netlify.com/drop>. You get a link right away.
- **Cloudflare Pages** or **GitHub Pages**: point it at this folder.

Then open the link on your phone:
- **iPhone (Safari):** Share → **Add to Home Screen**.
- **Android (Chrome):** ⋮ menu → **Install app**, or ⚙ → **Install app** inside LiftLog.

## Your data

Everything is stored in the browser on that device (`localStorage`). Nothing is sent anywhere.
Use ⚙ → **Export backup** from time to time. On iPhone this opens the share sheet, so you can save the backup to Files or iCloud. Use **Import backup** to restore it or move to a new phone.

## Updating the app

Upload the changed files, then change `CACHE` in `sw.js` (e.g. `liftlog-v2`). Phones pick up the new version the next time the app opens and use it from the launch after that.

## Try it locally

```sh
cd workout
npx http-server -p 8080     # or: python3 -m http.server 8080
```
Then open <http://localhost:8080>.
