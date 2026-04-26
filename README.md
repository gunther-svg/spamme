# Spamme - Ethical Bulk Email Application

This is a PHP application for sending bulk emails using PHPMailer.

## Prerequisites

- PHP 8.0 or higher
- MySQL database
- Composer

## Installation

1. Clone the repository:
   ```bash
   git clone <your-repository-url>
   cd spamme
   ```

2. Install PHP dependencies:
   If you have composer installed globally:
   ```bash
   composer install
   ```
   Or if using a local `composer.phar`:
   ```bash
   php composer.phar install
   ```

3. Set up your environment variables:
   Copy the example environment file and update it with your credentials:
   ```bash
   cp .env.example .env
   ```
   Edit `.env` to configure your database and SMTP settings (e.g., your Gmail and App Password).

4. Configure the web server:
   Ensure your web server (Apache/Nginx) points its document root appropriately so that the `public/` directory or project root is served.
   Update the `APP_URL` in `.env` to match your local setup.

## Usage

- Access the web interface at `http://localhost/spamme` (or whatever your `APP_URL` is).
- Configure your campaigns and queue emails.

## License

This project is licensed under the MIT License.
