# Picklist

Picklist is a CLI for people who save too many YouTube videos.

Turn a YouTube playlist into a ranked weekly watch queue.

It reads a dedicated YouTube playlist with `yt-dlp`, enriches videos with metadata and subtitles, ranks the backlog, and writes the top picks into an Obsidian daily note.

It is designed to be forked and run from source on macOS first.

## What You Get

- Guided onboarding with `install`
- Health checks with `doctor`
- Playlist sync from a dedicated YouTube playlist ID
- Metadata and subtitle enrichment with `yt-dlp`
- Hybrid ranking:
  - content quality tier inspired by `label_and_rate`
  - weekly priority score for "what should I watch this week?"
- Daily note publishing to a managed `## Watch This Week` section
- Scheduler support through Laravel Zero and cron

## Example Output

Picklist manages one section in your note:

```md
## Watch This Week

- [AI and Human Creativity](<https://youtube.com/watch?v=abc123>) | Thoughtful Channel | 25m | A Tier | High-upside ai pick with strong weekly relevance.
- [Better Systems for Deep Work](<https://youtube.com/watch?v=def456>) | Focus Lab | 18m | B Tier | Timely productivity video worth prioritizing this week.
```

Everything else in the note is preserved.

## Why I Built It

I spend a lot of time on YouTube while running, and I regularly find videos I want to watch later.

The problem is that my watch queue keeps growing faster than I can get through it.

As the backlog grows, great videos get buried. I know I am probably missing high-quality videos simply because they are lost in the queue.

Picklist solves that problem by helping me surface the best videos each week, so I can actually watch them instead of letting them disappear into a growing playlist backlog.

## Requirements

- PHP 8.2+
- Composer
- `yt-dlp`
- macOS
- An Obsidian vault on disk

## Quickstart

Clone the repo and install dependencies:

```bash
composer install
```

Run the guided installer:

```bash
php picklist install
```

Check the setup:

```bash
php picklist doctor
```

Run the full workflow manually:

```bash
php picklist queue:run
```

## First Run Example

```bash
php picklist install
php picklist doctor
php picklist queue:run
```

Example playlist ID:

```text
PL_EXAMPLE_PLAYLIST_ID
```

During install, you will point Picklist at:

- your playlist ID
- your Obsidian vault root
- your daily note path pattern
- an optional browser cookies source for private playlists
- your preferred local data directory

## Onboarding Flow

`install` asks for:

- playlist ID
- Obsidian vault root
- daily note path pattern
- timezone
- weekly pick count
- section heading
- browser cookies source for private YouTube access (optional)
- local data directory
- `yt-dlp` binary path
- whether `picklist` should be installed into `~/.local/bin`
- whether scheduling should be enabled

If scheduling is enabled, the installer can also offer to install a cron entry for:

```bash
php picklist schedule:run
```

The app stores local user config outside the git repo, in `~/.picklist` by default, or in `PICKLIST_HOME` if you set it.

The install flow can also symlink `picklist` into `~/.local/bin`, so users can run `picklist install` and `picklist queue:run` from anywhere once that directory is on their `PATH`.

## Commands

```bash
php picklist install
php picklist doctor
php picklist queue:sync
php picklist queue:rank
php picklist note:publish-weekly --date=2026-03-17
php picklist queue:run
```

## Daily Note Pattern

The note path pattern is relative to your vault root. Supported tokens are:

- `{year}`
- `{month_number}`
- `{month_number_padded}`
- `{month_name}`
- `{month_short}`
- `{day_number}`
- `{day_number_padded}`
- `{day_name}`
- `{day_short}`

Example:

```text
daily/{month_number_padded} {month_name}/{day_number_padded} {month_name}.md
```

For March 17, 2026, that resolves to:

```text
daily/03 March/17 March.md
```

## Important Constraint

V1 does **not** read YouTube `Watch Later` directly.

Use a dedicated playlist instead. The app expects a playlist ID such as:

```text
PL_EXAMPLE_PLAYLIST_ID
```

From that ID, Picklist builds the normal YouTube playlist URL:

```text
https://www.youtube.com/playlist?list=YOUR_PLAYLIST_ID
```

If the playlist is private, Picklist can pass `--cookies-from-browser` to `yt-dlp` when you configure a browser source during install.

That keeps the app simpler, more stable, and friendlier for a public repo.

## Why A Dedicated Playlist

YouTube `Watch Later` is convenient, but it is a poor public-first integration target.

It is private by default, awkward to access reliably through official APIs, and tends to push an app toward browser-auth hacks or scraping. A dedicated playlist is much easier for other people to fork, understand, and configure on their own systems.

The tradeoff is small: instead of reusing `Watch Later`, you save videos into a dedicated playlist that Picklist manages as your source queue.

## Scheduler

Laravel Zero schedules:

- `queue:sync` daily at `07:00`
- `note:publish-weekly` every Monday at `08:00`

The cron entry should run every minute:

```cron
* * * * * cd /path/to/repo && /opt/homebrew/bin/php picklist schedule:run >> /dev/null 2>&1
```

## Local Data

The app keeps user-specific state outside the repo:

- `config.json`
- `queue.json` inside the configured data directory

No private paths, secrets, or local snapshots need to be committed.

## Naming

- GitHub repo: `phaybein/picklist`
- Composer package: `phaybein/picklist`

## Development

Run tests:

```bash
composer test
```

Format the code:

```bash
composer lint
```

The automated test suite covers install, doctor, sync, rank, run, and weekly note publishing flows.

## Troubleshooting

`doctor` should be your first stop.

Common setup issues:

- the playlist ID is wrong
- the playlist is private but no browser cookies source is configured
- `yt-dlp` is not installed or not executable
- the vault root path is wrong
- the daily note pattern does not match your vault layout
- cron is not installed even though scheduling is enabled
