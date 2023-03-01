To run you must:
 1. Clone this repo
 2. import the emp.sql file into your local MySQL or MariaDB server
 
 Or you can simply choose an existing database you have, and create your own table with this create table statement:
 ```sql
 CREATE TABLE `employees` (
`person_id` int(7) unsigned NOT NULL,
`first_name` varchar(50) NOT NULL DEFAULT '',
`last_name` varchar(50) NOT NULL DEFAULT '',
`email_address` varchar(100) NOT NULL DEFAULT '',
`hire_date` date NOT NULL DEFAULT '0000-00-00',
`job_title` varchar(100) NOT NULL DEFAULT '',
`agency_num` int(7) unsigned DEFAULT NULL,
`registration_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
PRIMARY KEY (`person_id`));
```
3. Go into `/helpers/db_conn.php` and change the database name, user, and password to what you wish to use
4. Assuming you have php installed, go to project root and run `php -S 127.0.0.1:8080`.
5. Finally open a browser and visit `localhost:8080`. The app should be running and working
