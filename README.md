Here is the content for your GitHub README file:

---

# Event Registration System

This is a simple event registration system built using PHP, JavaScript, HTML, and CSS with a CSV file acting as the local database.

## Features

- Event registration form with client-side and server-side validation.
- Admin login functionality (username: `Yasin1122`, password: `password123`).
- Limits admin login attempts to 3 per user; IP address is banned after 3 failed attempts.
- Admin dashboard to view and manage event registrations.
- Export registrations to CSV (Excel-compatible).
- CRUD operations for event management.
- IP ban tracking via `banned.csv`.

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/yasinULLAH/Event-registration-system.git
   ```
   
2. Place the `event_registration.php` file on a PHP-enabled server.
   
3. Ensure the server has write permissions for the following files:
   - `registrations.csv`
   - `events.csv`
   - `banned.csv`

4. Access the file via your web browser.

## Usage

### User Registration

- Users can register for available events by filling out the registration form.
- Fields include:
  - Name
  - Email
  - Phone number
  - Event selection
  - Comments (optional)

### Admin Dashboard

- Navigate to the admin login form and enter the credentials:
  - Username: `Yasin1122`
  - Password: `password123`
  
- Once logged in, you can:
  - View all registered users.
  - Export registrations to CSV.
  - Add, edit, or delete events.

## Security Notes

- Admin login limited to 3 attempts per IP. After 3 failed attempts, the IP will be banned and logged in `banned.csv`.
- Ensure that the CSV files are stored securely and are not publicly accessible.

## License

This project is licensed under the MIT License.

---
