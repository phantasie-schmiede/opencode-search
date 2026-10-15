# opencode-search

Full-text search across all [OpenCode](https://opencode.ai) sessions. Finds which
sessions mention a keyword — in user prompts, assistant answers, tool output, or
reasoning — and shows the matching lines in context.

opencode stores every session locally in a SQLite database; `opencode-search`
queries it read-only. There is no dedicated session search in the opencode CLI,
this tool provides one.

## Requirements

- [opencode](https://opencode.ai) (sessions must exist locally)
- PHP >= 8.2 (CLI) with the `pdo_sqlite` and `mbstring` extensions

## Installation

Install to `~/.local/bin` (add it to your `PATH` if needed):

```sh
curl -fsSL https://raw.githubusercontent.com/phantasie-schmiede/opencode-search/main/bin/opencode-search \
  -o ~/.local/bin/opencode-search && chmod +x ~/.local/bin/opencode-search
```

Or from a clone:

```sh
git clone https://github.com/phantasie-schmiede/opencode-search.git
ln -s "$PWD/opencode-search/bin/opencode-search" ~/.local/bin/opencode-search
```

## Usage

```
opencode-search <keyword> [options]
opencode-search -- <keyword starting with ->

Options:
  -s, --show SESSID    Show matching lines of a session with context
                       (SESSID: full ID or unique prefix)
  -l, --limit N        Max. number of sessions in the list (default: 20)
  -c, --context N      Context lines per match for --show (default: 2)
      --text-only      Search text parts only (no tool output)
  -p, --project DIR    Filter by project/directory name (substring)
      --since DATE     Only sessions from DATE on (YYYY-MM-DD or -Nd, e.g. -14d)
      --json           Output as JSON (list mode only)
      --resume         Show the opencode --session command per session
  -q, --quiet          Print session IDs only
  -V, --version        Print the version and exit
  -h, --help           This help

Exit status:  0 = matches found, 1 = no matches, 2 = error
```

Examples:

```sh
opencode-search "asset:publish"
opencode-search "asset:publish" --since -7d --resume
opencode-search "Could not determine path" --show ses_ec4671
opencode-search "vendor/bin/crafter" --json
opencode-search -- "--leading-dash-keyword"
```

List mode prints sessions sorted by match count, then by recency:

```
    46  2026-10-14 18:07  ses_ec4671dedffehRMCATSc1Irbbp  ListCommands Hilfetext: typo3 statt crafter
    15  2026-09-28 08:19  ses_f18e6cf35ffeukOa5p2KHaczzx  Testsetup für TYPO3 12–14 und PHP
```

`--show` prints each matching part with its role (`user`, `assistant`,
`tool:<name>`, `reasoning`), timestamps, line numbers, context lines, and
highlighted matches (on TTY output):

```
[2026-10-14 19:05] tool:bash
     51              if (!getenv('TYPO3_SKIP_ASSET_PUBLISH')) {
   > 52                  $command = ['asset:publish'];
     53                  if ($event->getIO()->isVerbose()) {
```

Resume a session afterwards with `opencode --session <sessionID>`.

## How it works

- Database location: `opencode db path` if the opencode binary is available,
  otherwise `~/.local/share/opencode/opencode.db`.
- The database is opened strictly read-only; the tool never writes.
- The search runs as a case-insensitive `LIKE` over the stored part data;
  `%`, `_`, and `\` in the keyword are treated literally (escaped).
- `--text-only` restricts the search to text parts (user prompts and assistant
  answers); by default, tool output and reasoning are included as well.
- Exit status `1` on "no matches" makes the tool scriptable, e.g. for
  completion or CI.

## Caveats

- A keyword that is typed or printed inside an active opencode session is
  itself stored in that session's parts, so searches run *within* opencode may
  match the current session.
- The tool assumes the current opencode storage schema
  (`session`, `message`, `part` tables with JSON in the `data` column). If
  opencode changes its storage format, the queries may need updating.

## Development

```sh
php tests/smoke.php
```

The smoke test builds a synthetic session database in a temporary `$HOME` and
verifies list/show modes, filters, escaping, JSON output, and exit codes.

## License

[GPL-3.0-or-later](LICENSE)
