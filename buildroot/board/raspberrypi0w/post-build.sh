#!/bin/sh

set -u
set -e

cd "$(dirname "$0")"

# Add a console on tty1
if [ -e ${TARGET_DIR}/etc/inittab ]; then
    grep -qE '^tty1::' ${TARGET_DIR}/etc/inittab || \
	sed -i '/GENERIC_SERIAL/a\
tty1::respawn:/sbin/getty -L  tty1 0 vt100 # HDMI console' ${TARGET_DIR}/etc/inittab
fi

cp -r skeleton/* "$TARGET_DIR"

rm ${TARGET_DIR}/etc/resolv.conf
echo "nameserver 8.8.8.8" > ${TARGET_DIR}/etc/resolv.conf
echo "nameserver 2001:4860:4860::8888" >> ${TARGET_DIR}/etc/resolv.conf

# create the /boot mountpoint
mkdir ${TARGET_DIR}/boot

ln -sf ld-2.33.so ${TARGET_DIR}/lib/ld-linux.so.3
