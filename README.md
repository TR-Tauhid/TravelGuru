ALBATROSS TRAVELS is a bus ticket purchasing platform that allows users to book tickets for various bus operators. The platform offers a user-friendly interface, allowing users to easily search for and book tickets for their desired routes. The website also provides information on bus schedules, fares, and other relevant details.

Bus Ticket System
Overview

This project is a web-based bus ticket booking system that uses PHP, MySQL, and Docker for an easy and consistent development environment.

Prerequisites

Docker

Docker Compose

Setup Instructions
1. Clone the Repository
git clone https://github.com/yourusername/your-repo-name.git
cd your-repo-name

2. Configure Environment Variables

Copy the example environment file and update it with your credentials.

cp .env.example .env


Edit the .env file and replace the placeholders with your actual values, for example:

DB_HOST=db
DB_NAME=bus_ticket
DB_USER=root
DB_PASS=dbPassword

APP_EMAIL=your-email@example.com
APP_PASSWORD=your-email-app-password

3. Build and Run the Containers

Run the following command to build and start the containers:

docker-compose up --build


This command will build the Docker images and start the web and database services.

4. Access the Application

Once the containers are running, you can access the application at:

http://localhost:8080

5. Initial Database Setup

On the first run, the database and necessary tables will be automatically created. If you need to reset the database, you can run:

docker-compose down -v
docker-compose up --build


This will remove the existing database volume and reinitialize it.

Project Structure

Dockerfile: Defines the PHP-Apache environment and installs dependencies.

docker-compose.yml: Configures the services for the web and database.

init.sql: Initializes the database and creates necessary tables.

main.php=> Contains the PHP source code and related assets.

scripts/: Contains the js functions and admin page.

vendor/: Contains PHP dependencies managed by Composer.

Contributing

Feel free to fork the repository and submit pull requests. For any issues or feature requests, please open an issue on the GitHub repository.

