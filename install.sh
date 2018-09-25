#!/bin/sh
#cp rsyslog/00-smartmeterMQTT.conf /etc/rsyslog.d
cp systemd/automationMQTT.service /lib/systemd/system/
systemctl enable automationMQTT
systemctl start automationMQTT
echo "automationMQTT installed and started"
