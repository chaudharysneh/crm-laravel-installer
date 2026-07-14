# Fablead CRM Local Installation Guide

This guide is for non-technical users. Follow it step by step and the CRM should run on your local machine.

## What you received

You should have one folder named something like:

```text
crm-laravel-installer
```

Inside it, keep these files together:

- `installer.php`
- `installer-config`
- `crm-fablead.zip`

Do not unzip `crm-fablead.zip` manually. The installer will do it for you.

## Step 1: Install XAMPP if you do not have it

If XAMPP is already installed, skip to Step 2.

Download XAMPP from the official website:

https://www.apachefriends.org/download.html

Install it normally. After installation, open the XAMPP Control Panel and start:

- Apache
- MySQL

Both should show as running.

## Step 2: Place the installer folder correctly

Copy the full `crm-laravel-installer` folder into XAMPP’s `htdocs` folder.

Usually on Windows, that folder is:

```text
C:\xampp\htdocs
```

So the final path should look like:

```text
C:\xampp\htdocs\crm-laravel-installer
```

If your XAMPP is installed somewhere else, use that XAMPP folder’s `htdocs`.

## Step 3: Open the installer in your browser

Open Chrome, Edge, or any modern browser and visit:

```text
http://localhost/crm-laravel-installer/installer.php
```

You should see the Fablead CRM installer screen.

## Step 4: Run the installer steps

The installer has 4 simple steps:

1. Extract the project
2. Connect the database
3. Run Laravel setup
4. Create admin account

During extraction, do not refresh or close the page.

## Step 5: Database details

If you are using normal XAMPP, use:

```text
Database Host: 127.0.0.1
Port: 3306
Username: root
Password: leave blank
```

Choose any database name, for example:

```text
crm_fablead_laravel
```

The installer will create the database if it does not already exist.

## Step 6: Create your admin account

Enter your name, email, and password. This will be your CRM login account.

After the installer finishes, it will take you to the CRM login page.

## Your CRM URL after installation

Use this URL to open the CRM:

```text
http://localhost/crm-laravel-installer
```

Login using the admin email and password you created in the installer.

## Common issues

If `localhost` does not open:

- Make sure Apache is running in XAMPP.

If database connection fails:

- Make sure MySQL is running in XAMPP.
- Try username `root` and leave password empty.

If the installer says `crm-fablead.zip` is missing:

- Make sure `crm-fablead.zip` is inside the same folder as `installer.php`.

If something looks old after changes:

- Press `Ctrl + F5` to hard refresh the browser.

## Quick checklist

- XAMPP installed
- Apache running
- MySQL running
- Folder placed inside `htdocs`
- Opened `http://localhost/crm-laravel-installer/installer.php`
- Completed all 4 installer steps
- Logged in at `http://localhost/crm-laravel-installer`

