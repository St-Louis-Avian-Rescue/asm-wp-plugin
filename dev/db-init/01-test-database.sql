-- Separate database for the PHPUnit suite, which wipes its tables on every run.
CREATE DATABASE IF NOT EXISTS wordpress_test;
GRANT ALL PRIVILEGES ON wordpress_test.* TO 'wordpress'@'%';
