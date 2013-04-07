#!/bin/bash

sudo mkdir -p /var/log/phpfunctions

sudo touch /var/log/phpfunctions/cmd1.log
sudo touch /var/log/phpfunctions/cmd2.log
sudo touch /var/log/phpfunctions/cmd3.log

sudo chmod -R 777 /var/log/phpfunctions

sudo touch /var/log/listener.log
sudo chmod 777 /var/log/listener.log
