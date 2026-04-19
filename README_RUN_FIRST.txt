Campus Governance System - Stable Demo Setup

What changed
- Reworked the app to run from one local PHP server.
- Kept the existing frontend/admin UI structure as much as possible.
- Rebuilt the SQLite database with clean demo data.
- Removed the dependency on the old Go backend for the demo flow.

How to run
1. Make sure PHP is installed on your PC.
2. Open a terminal in this project folder.
3. Run:
   php -S 127.0.0.1:8000 router.php
4. Open:
   http://127.0.0.1:8000/

Windows quick start
- Double-click run_project.bat
- Then open http://127.0.0.1:8000/

Demo accounts
- Admin:
  login: admin
  email: admin@university.edu
  password: admin12345

- Faculty:
  login: faculty
  email: faculty@university.edu
  password: faculty12345

- Student:
  login: student
  email: student@university.edu
  password: student12345

Demo tracking tokens already in database
- CGS-DEMO-0001
- CGS-DEMO-0002
- CGS-DEMO-0003

Main demo flow
- Sign up works
- Sign in works
- Submit a report
- A token is generated
- Home/dashboard stats update from the database
- Admin/faculty can access the admin panel after login
- Admin can review/edit/update report status
- Report submitter can track the report using the token

Important note
- This package expects a PHP build with SQLite enabled. XAMPP/WAMP/Laragon usually includes it.
