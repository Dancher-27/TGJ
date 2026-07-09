# The Greatest Journey

A fansite/wiki-style web app for the original story **The Greatest Journey** — built with PHP, MySQL and vanilla CSS/JS.

## Features

- **Home** — landing page with intro
- **Characters** — character profiles with stats, abilities, backstory and relations
- **Databook** — detailed character encyclopedia with stat bars and formatted descriptions
- **Timeline** — snake-layout timeline of all story events with pagination and filters
- **Seizoenen** — season overview with hero banner, arc-grouped events and character chips
- **Lore** — world-building articles
- **Gallery** — image gallery
- **Admin panel** — manage all content (characters, arcs, seasons, timeline events, lore, gallery, factions)

## Tech Stack

- PHP 8+
- MySQL / MariaDB
- Vanilla CSS & JavaScript (no frameworks)
- XAMPP (local development)

## Setup

### 1. Requirements
- XAMPP (or any Apache + PHP + MySQL stack)
- PHP 8.0+
- MySQL 5.7+ / MariaDB

### 2. Database
1. Open phpMyAdmin
2. Create a database named `tgj`
3. Import `database.sql`

### 3. Connection
1. Copy `includes/connection.example.php` to `includes/connection.php`
2. Fill in your database credentials:
```php
$conn = new mysqli("localhost", "your_username", "your_password", "tgj");
```

### 4. Uploads
Make sure these folders exist and are writable:
```
uploads/characters/
uploads/gallery/
uploads/lore/
uploads/seasons/
```

### 5. Run
Place the project in your XAMPP `htdocs` folder and open:
```
http://localhost/project-tgj/
```

Admin panel:
```
http://localhost/project-tgj/admin/
```
Default login: `admin` / `admin123` — **change this after first login**

## Project Structure

```
project-tgj/
├── admin/              # Admin panel (CRUD for all content)
├── includes/
│   ├── connection.php  # DB credentials (not in repo)
│   ├── helpers.php     # renderDescription() parser
│   ├── fonts.php       # Google Fonts include
│   └── navbar.php      # Shared navigation
├── uploads/            # User-uploaded images
├── styles.css          # Main stylesheet
├── database.sql        # Full database schema
├── index.php
├── characters.php
├── character.php
├── databook.php
├── timeline.php
├── seasons.php
├── lore.php
└── gallery.php
```
