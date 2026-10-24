# Songbook Manager

Songbook Manager is a web application for maintaining a shared database of songs with chords and for compiling them into printable songbooks. It is deployed at [spevnik.mustangy.sk](https://spevnik.mustangy.sk) and was developed for the 24th Scout troop *Mustangy*, though it is not specific to any one group.

The user interface, most code comments and the roadmap are in Slovak. Issues and pull requests may be written in English.

## Objectives

The project aims to:

- Provide a central song database, primarily for Slovak and Czech repertoire, to which any registered user can add new songs or correct existing ones.
- Allow communities to compile their own songbooks from that database, restricted to their members and ready for printing.
- Remain free of charge and simple to operate: the application uses plain PHP and MySQL, requires no build step and runs on ordinary shared hosting.

User contributions are recognised through an experience-point system (adding and editing songs earns points, with weekly, monthly and overall leaderboards). It is intended as an incentive to keep the database maintained rather than as a core feature.

## Features

- Songs with chords: transposition, capo handling, chorus markers, auto-scroll, public and private visibility
- Search and filtering by category, tag, instrument and difficulty; song metadata such as strumming pattern, chord source, original artist and translator
- Per-song edit history and error reporting
- Songbooks: adding, removing and reordering songs and categories; automatic table of contents and page numbering; saved render presets (fonts, chord layout)
- Songbook locking, which stores a snapshot of the songs so that a printed edition can be reproduced later
- Client-side PDF export, with optional storage of the finished PDF on the server
- Groups with members, administrators and checklists (CSV export)
- A wishlist of songs requested by users
- A questionnaire module for collecting which songs members know or prefer
- Light, dark and system colour themes

The roadmap is maintained in [plan/plan.md](plan/plan.md) (in Slovak).

## Technology

| Component | Technology |
|---|---|
| Backend | PHP 8.1+, `mysqli`, no framework |
| Database | MySQL / MariaDB |
| Frontend | Bootstrap 4 (bundled in `bootstrap/`), vanilla JavaScript |
| PDF generation | `html2canvas` and `jsPDF`, loaded from cdnjs and executed in the browser |

## Repository structure

```
*.php            public pages (songs, songbooks, groups, users, wishlist, ...)
php/             shared helpers and the AJAX and form endpoints used by the pages
php/subpage/     smaller endpoints loaded into pages
admin/           administration tools: song catalogue, statistics, activity log, questionnaires
anketa/          public questionnaire pages
js/              chord parsing and rendering, layout, song ordering, preview
styles/          stylesheets, including the A4 page styles used for printing
sql/             database migrations
plan/            public roadmap page
```

## Running locally

Requirements: PHP 8.1 or newer with the `mysqli` extension, and a MySQL or MariaDB server.

1. Clone the repository and create an empty database.
2. Create `php/db.php`. This file is excluded from version control and must be provided for each environment. The application expects a `mysqli` connection in the variable `$mysqli`:

   ```php
   <?php
   $mysqli = new mysqli('localhost', 'user', 'password', 'songbook');
   $mysqli->set_charset('utf8mb4');
   ```

3. If server-side PDF storage is required, make the `songbook_pdfs/` directory writable by the web server.
4. Serve the repository root, for example with `php -S localhost:8000`.

## Contributing

Contributions are welcome. If you are unsure whether a change fits the project, please open an issue before starting work. For code changes:

- Branch from `testing`; `main` reflects the deployed version.
- Keep pull requests small and focused, and follow the style of the surrounding code.
- Use prepared statements for all new SQL.
- Do not commit `php/db.php`, generated PDFs or images.

## Song content and copyright

Lyrics and chords in the database are submitted by users for personal use, and submitting a song is the responsibility of the submitter. Songs are removed at the author's request. This repository contains source code only, not song data.

## Contact

Maintainer: Jakub Hlavatý, jakubo.hlavaty@gmail.com

Security issues should be reported by email rather than as public issues.