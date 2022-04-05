#!/bin/bash

# disable HDMI
/usr/bin/tvservice -o

# uncomment to enable full system boot
# exec /sbin/init

mount -o ro /dev/mmcblk0p1 /boot
mount -t sysfs none /sys
mount -t proc proc /proc
mount -a

modprobe i2c-bcm2708
modprobe i2c_dev
modprobe hwmon
modprobe i2c_bcm2835
modprobe raspberrypi_hwmon
modprobe bcm2835_rng
modprobe rng_core
modprobe brcmfmac
modprobe brcmutil
modprobe ip_tables
modprobe x_tables
modprobe cfg80211
modprobe ipv6

rngd -o /dev/random -r /dev/urandom

# execute main script
php /solarcampi/run.php

# boot full system?
if [ $? == 42 ]; then
	exec /sbin/init
fi

# this should never happen :)
# disable power here, let's try again later
echo 4 > /sys/class/gpio/export
echo out > /sys/class/gpio/gpio4/direction
echo 1 > /sys/class/gpio/gpio4/value
sleep 1
echo 0 > /sys/class/gpio/gpio4/value
