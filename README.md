Buildroot for the SolarCamPi
====================================

This repo contains Buildroot for the SolarCamPi

Check
https://github.com/Manawyrm/SolarCamPi-Buildroot/tree/main/buildroot/board/raspberrypi0w/skeleton/solarcampi
for the interesting bits

## Flashing

After flashing the latest release, mount the FAT32 partition and change the solarcampi.ini accordingly.  
Ensure a file called "id_rsa" (SSH RSA private key for the ssh tunnel) exists.  
Ensure a file called "authorized_keys" (list of public keys) exists.  

Tested with Buildroot 2021.08
