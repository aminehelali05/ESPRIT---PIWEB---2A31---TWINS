# Diversity — Professional Networking Platform

<p align="center">
  <img src="https://readme-typing-svg.herokuapp.com?font=Poppins&size=30&duration=3000&pause=700&color=4ABAF7&center=true&vCenter=true&width=850&lines=Diversity+%7C+Professional+Networking+Platform;Build%2C+Connect%2C+Collaborate%2C+Grow" />
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white" />
  <img src="https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white" />
  <img src="https://img.shields.io/badge/JavaScript-323330?style=for-the-badge&logo=javascript&logoColor=F7DF1E" />
  <img src="https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white" />
  <img src="https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white" />
</p>

---

## Overview

Diversity is a PHP/MySQL web platform designed for professionals, freelancers, and clients to connect, publish opportunities, manage projects, and collaborate through a clean front office and back office experience.

It includes:
- secure authentication and registration
- user profiles with avatar and account management
- project management for clients and admins
- job offers and freelancer applications
- contract creation and lifecycle tracking
- social and challenge-style community areas
- admin back office for moderation and operations

---

<!-- TECH STACK ANIMATED LINE -->
<p align="center">
  <img src="https://readme-typing-svg.herokuapp.com?font=Poppins&size=28&duration=3000&pause=700&color=4ABAF7&center=true&vCenter=true&width=700&lines=Tech%20Stack" />
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-%23777BB4.svg?style=for-the-badge&logo=php&logoColor=white" />
  <img src="https://img.shields.io/badge/MySQL-%234479A1.svg?style=for-the-badge&logo=mysql&logoColor=white" />
  <img src="https://img.shields.io/badge/JavaScript-%23323330.svg?style=for-the-badge&logo=javascript&logoColor=%23F7DF1E" />
  <img src="https://img.shields.io/badge/HTML5-%23E34F26.svg?style=for-the-badge&logo=html5&logoColor=white" />
  <img src="https://img.shields.io/badge/CSS3-%231572B6.svg?style=for-the-badge&logo=css3&logoColor=white" />
  <img src="https://img.shields.io/badge/AJAX-007ACC?style=for-the-badge&logo=axios&logoColor=white" />
  <img src="https://img.shields.io/badge/JSON-000000?style=for-the-badge&logo=json&logoColor=white" />
  <img src="https://img.shields.io/badge/Git-F05032?style=for-the-badge&logo=git&logoColor=white" />
  <img src="https://img.shields.io/badge/GitHub-181717?style=for-the-badge&logo=github&logoColor=white" />
  <img src="https://img.shields.io/badge/XAMPP-FB7A24?style=for-the-badge&logo=xampp&logoColor=white" />
</p>

---

## Main Modules

### Front Office
- `Home` — dashboard-style landing page for logged-in users
- `Auth` — sign in and sign up flow
- `Profile` — update profile data, avatar, password, and account state
- `Projects` — browse and interact with projects
- `Job Offers` — view opportunities and apply as a freelancer
- `Contracts` — review contracts and contract status
- `Challenges` — community challenge area
- `Social` — social feed and networking space

### Back Office
- `Dashboard` — admin overview and quick actions
- `Projects` — create, update, and delete projects
- `Job Offers` — manage offers and applications
- `Contracts` — create and track contracts

### Core Backend
- `Controllers/` — application logic for users, projects, job offers, and contracts
- `Models/` — domain entities used by the controllers and views
- `Views/` — front office and back office UI pages
- `config.php` — database connection bootstrap
- `User.sql` — database schema and sample structure

---

## Key Features

- **Authentication:** login, register, logout, and session-based access control
- **Role handling:** user, freelancer, client, and admin flows
- **Profile management:** avatar upload, password update, and delete-request workflow
- **Job offer workflow:** application submission, acceptance, rejection, and status tracking
- **Contract workflow:** client-side contract creation and administration
- **Admin tools:** secured back office with CSRF protection
- **Responsive UI:** custom CSS assets for each section of the website

---

## Project Structure

```text
Diversity/
├─ index.php
├─ config.php
├─ User.sql
├─ Controllers/
├─ Models/
├─ Views/
│  ├─ FrontOffice/
│  └─ BackOffice/
└─ assets/
   ├─ css/
   ├─ js/
   └─ images/
```

---

## Setup

### Requirements
- PHP 8+ recommended
- MySQL or MariaDB
- Apache/Nginx local server, or XAMPP/WAMP/MAMP

### Installation
1. Clone the repository.
2. Copy the project into your web server directory.
3. Create a database for the project.
4. Import `User.sql` into your database.
5. Update database credentials in `config.php` if needed.
6. Start Apache and MySQL.
7. Open the app through `index.php` in your browser.

### Local Run Example
```bash
php -S localhost:8000
```

Then open:
```text
http://localhost:8000/index.php
```

---

## Admin Access

The back office is protected and redirects unauthorized users to the authentication page. Admin pages live in `Views/BackOffice/` and rely on authenticated sessions plus CSRF checks.

---

## Database

The database includes tables for:
- users
- projects
- job offers
- job offer applications
- contracts

Make sure the schema in `User.sql` matches your local database before starting the app.

---

## Screens & UI

The interface uses dedicated CSS files in `assets/css/` for:
- `home.css`
- `profile.css`
- `projects.css`
- `reviews.css`
- `social.css`
- `skills.css`
- `challenges.css`
- `auth.css`
- `backoffice-dashboard.css`

The app also uses custom JavaScript behavior from `assets/js/` for dashboards, profile actions, project interactions, job offers, and social features.

---

<p align="center">
  <a href="https://github.com/aminehelali05/ESPRIT---PIWEB---2A31---TWINS">
    <img src="https://visitcount.itsvg.in/api?id=aminehelali05&icon=0&color=0" alt="visit count" />
  </a>
</p>

<!-- SOCIALS ANIMATED LINE -->
<p align="center">
  <img src="https://readme-typing-svg.herokuapp.com?font=Poppins&size=28&duration=3000&pause=700&color=4ABAF7&center=true&vCenter=true&width=700&lines=Socials" />
</p>

<p align="center">
  <a href="https://instagram.com/amiine.helali">
    <img src="https://img.shields.io/badge/Instagram-%23E4405F.svg?style=for-the-badge&logo=Instagram&logoColor=white" />
  </a>
  <a href="mailto:amine.helali@esprit.tn">
    <img src="https://img.shields.io/badge/Email-D14836?style=for-the-badge&logo=gmail&logoColor=white" />
  </a>
</p>

---

## Notes

- The repository currently uses a classic PHP structure instead of a modern framework.
- The README is written to match the project’s front office and back office modules.
- You can expand it later with screenshots, deployment steps, or a live demo link.

<!-- Proudly created with GPRM ( https://gprm.itsvg.in ) -->
