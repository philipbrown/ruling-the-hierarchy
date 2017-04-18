#!/bin/bash
#

service mysql start

echo "CREATE DATABASE IF NOT EXISTS test" | mysql
echo "CREATE USER 'user'@'localhost' IDENTIFIED BY 'password'" | mysql
echo "GRANT ALL PRIVILEGES ON test.* TO 'user'@'localhost' IDENTIFIED BY 'password'" | mysql

composer install

vendor/bin/phpunit
