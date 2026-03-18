# Picklist

`Picklist` is a public-first Laravel Zero CLI for people who save too many YouTube videos.

It watches a dedicated YouTube playlist feed, enriches videos with `yt-dlp`, ranks them with a weekly-priority model, and writes the top picks into an Obsidian daily note.

This project is designed to be forked and run from source on macOS first.

## What It Does

- Guided onboarding with `install`
- Health checks with `doctor`
- Feed sync from a dedicated playlist RSS/feed URL
- Metadata and subtitle enrichment with `yt-dlp`
- Hybrid ranking:
  - content quality tier inspired by `label_and_rate`
  - weekly priority score for "what should I watch this week?"
- Daily note publishing to a managed `## Watch This Week` section
- Scheduler support through Laravel Zero and cron

## Important Constraint

V1 does **not** read YouTube `Watch Later` directly.

Use a dedicated playlist instead. The app expects a playlist RSS/feed URL such as:

```text
https://www.youtube.com/feeds/videos.xml?playlist_id=YOUR_PLAYLIST_ID
```

That keeps the app simpler, more stable, and friendlier for a public repo.

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

## Onboarding Flow

`install` asks for:

- playlist feed URL
- Obsidian vault root
- daily note path pattern
- timezone
- weekly pick count
- section heading
- local data directory
- `yt-dlp` binary path
- whether `picklist` should be installed into `~/.local/bin`
- whether scheduling should be enabled

If scheduling is enabled, the installer can also offer to install a cron entry for:

```bash
php picklist schedule:run
```

The app stores local user config outside the git repo, in `~/.picklist` by default, or in `YT_SUGGESTIONS_HOME` if you set it.

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

## Example Output

The app manages one section in your note:

```md
## Watch This Week

- [AI and Human Creativity](<https://youtube.com/watch?v=abc123>) | Thoughtful Channel | 25m | A Tier | High-upside ai pick with strong weekly relevance.
- [Better Systems for Deep Work](<https://youtube.com/watch?v=def456>) | Focus Lab | 18m | B Tier | Timely productivity video worth prioritizing this week.
```

Everything else in the note is preserved.

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
- `data/queue.json`

No private paths, secrets, or local snapshots need to be committed.

## Development

Run tests:

```bash
composer test
```

Format the code:

```bash
composer lint
```

## Troubleshooting

`doctor` should be your first stop.

Common setup issues:

- the playlist feed URL is wrong
- `yt-dlp` is not installed or not executable
- the vault root path is wrong
- the daily note pattern does not match your vault layout
- cron is not installed even though scheduling is enabled
