# TGJ
The Greatest Journey — Fansite Webapplicatie

Dit is een fansite / wiki webapplicatie voor het verhaal The Greatest Journey.
De applicatie is gebouwd met PHP en MySQL en bevat uitgebreide informatie over personages, verhaallijnen en de wereld.

Het project laat zien hoe je een content-driven webapplicatie bouwt met een admin panel en meerdere gekoppelde databronnen.

Functionaliteiten

Homepagina
Introductie van het verhaal

Characters
Profielen met statistieken, abilities en achtergrondinformatie

Databook
Uitgebreide encyclopedie met karakterinformatie en stat bars

Timeline
Overzicht van alle gebeurtenissen in het verhaal met filters en paginering

Seizoenen
Overzicht van verhaallijnen per seizoen

Lore
Artikelen over de wereld en achtergrond

Gallery
Afbeeldingen van personages en scenes

Admin panel
Beheer van alle content (characters, timeline, lore, gallery, etc.)

Gebruikte technologieën

PHP (OOP)

MySQL / MariaDB

HTML, CSS, JavaScript

XAMPP (lokale ontwikkeling)

Projectstructuur
project-tgj/

├── admin/              # Admin panel (CRUD functionaliteit)
├── includes/
│   ├── connection.php
│   ├── helpers.php
│   └── navbar.php
│
├── uploads/            # Geüploade afbeeldingen
├── styles.css          # Styling
├── database.sql        # Database schema
│
├── index.php
├── characters.php
├── character.php
├── databook.php
├── timeline.php
├── seasons.php
├── lore.php
└── gallery.php
Vereisten

PHP 8.0 of hoger

MySQL of MariaDB

Lokale server zoals XAMPP

Installatie

Plaats het project in je webserver map:

/xampp/htdocs/project-tgj/

Maak een database aan:

tgj

Importeer het bestand:

database.sql

Configureer de database connectie:

includes/connection.php
$conn = new mysqli("localhost", "root", "", "tgj");

Zorg dat de upload mappen bestaan:

uploads/characters/
uploads/gallery/
uploads/lore/
uploads/seasons/

Start de applicatie:

http://localhost/project-tgj/
Admin panel

Toegang tot het admin panel:

http://localhost/project-tgj/admin/

Standaard login:

Gebruiker: admin

Wachtwoord: admin123

⚠️ Wijzig deze gegevens na het eerste gebruik.

Wat dit project laat zien

Met dit project demonstreer ik:

bouwen van een content management systeem (CMS-achtig)

werken met meerdere database tabellen en relaties

CRUD functionaliteit via een admin panel

bestand uploads

structureren van een grotere PHP applicatie

Doel van dit project

Dit project is onderdeel van mijn software development portfolio en laat zien hoe je een uitgebreide webapplicatie bouwt met veel content en beheerfunctionaliteit.
