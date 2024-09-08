
# STGHCS Backend

## Description

The **STGHCS Backend** project is a robust backend system designed to manage the operations of a scalable healthcare service platform. It supports seamless user management, appointment scheduling, and data processing while ensuring the privacy and security of sensitive patient information.

Built using the Laravel framework, this backend is designed for high performance and reliability, offering APIs for mobile and web applications to interact with core healthcare management features. It includes functionalities like authentication, role-based access control, real-time notifications, and advanced reporting.

With a strong emphasis on scalability, security, and ease of integration, this backend is suitable for large-scale healthcare operations. The system leverages cloud storage, database management, and third-party services for email notifications, secure document uploads, and automated report generation.

## Key Features

- **User Management:** Handle different roles such as patients, healthcare providers, and administrators with customizable permissions.
- **Appointment Scheduling:** A comprehensive scheduling system allowing healthcare providers to manage appointments and availability.
- **Secure File Uploads:** Efficient document management system for uploading, storing, and retrieving sensitive files, with support for Cloudinary integration.
- **Notifications:** Real-time notifications for important updates such as appointment reminders and status changes.
- **Reporting and Analytics:** Detailed reports on system usage, patient interactions, and healthcare provider activities.

## Technology Stack

- **Backend Framework:** Laravel 11
- **Database:** MySQL
- **Cloud Services:** Linode (Server) and Cloudinary (for document handling)
- **Third-party Integrations:** Mailtrap for email services
- **Authentication:** Role-based access control using Spatie Laravel permissions

This project serves as the backbone for an efficient and scalable healthcare service, ready to handle the complexities of healthcare management in a digital-first world.


## Table of Contents
- [Description](#description)
- [Folder Structure](#folder-structure)
- [Installation](#installation)
- [Usage](#usage)
- [Contributing](#contributing)
- [License](#license)

## Folder Structure

The project has the following folder structure:

```
stghcs/
│
├── app/                     # Core application files
├── bootstrap/               # Framework bootstrap files
├── config/                  # Configuration files
├── database/                # Migrations and seeds
│   ├── factories/           # Model factories
│   ├── seeders/             # Seeder files
│   └── migration/           # Migration files
├── public/                  # Public-facing assets (CSS, JS, images)
├── resources/               # Views and raw assets like CSS and JavaScript files
│   └── views/               # Blade template views
├── routes/                  # Route definitions
│   ├── api.php              # Api routes
│   └── web.php              # Web routes
├── storage/                 # Log files and cached data
├── tests/                   # Automated tests
├── vendor/                  # Composer dependencies
├── .env                     # Environment configuration file
├── composer.json            # Composer dependencies and scripts
├── package.json             # NPM package configuration
├── artisan                  # Artisan CLI for Laravel
└── README.md                # Project documentation
```

## Installation

Follow the steps below to get the project running locally:

1. **Clone the repository:**
   ```bash
   git clone https://github.com/yourusername/projectname.git
   cd projectname
   ```

2. **Install PHP dependencies:**
   Ensure you have [Composer](https://getcomposer.org/) installed and run:
   ```bash
   composer install
   ```

3. **Install NPM dependencies:**
   Ensure you have [Node.js](https://nodejs.org/) installed and run:
   ```bash
   npm install
   ```

4. **Create an environment file:**
   Copy the `.env.example` to `.env` and modify it based on your environment:
   ```bash
   cp .env.example .env
   ```

5. **Generate an application key:**
   Run the following Artisan command:
   ```bash
   php artisan key:generate
   ```

6. **Set up the database:**
   - Configure the database connection in your `.env` file.
   - Run the migrations:
   ```bash
   php artisan migrate
   ```

7. **Serve the application:**
   Start the development server with:
   ```bash
   php artisan serve
   ```

## Usage
After installation, open `http://localhost:8000` in your web browser to view the application.

## Testing
To run the automated tests:
```bash
php artisan test
```

## Contributing
If you'd like to contribute to this project, please follow the guidelines below:
1. Fork the repository.
2. Create a new branch for your feature (`git checkout -b feature-branch-name`).
3. Commit your changes (`git commit -am 'Add new feature'`).
4. Push to the branch (`git push origin feature-branch-name`).
5. Create a Pull Request.

## License
This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
