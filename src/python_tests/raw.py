#!/usr/bin/env python

from __future__ import division
from __future__ import print_function

# test and code samples related getting to raw pixels values

__author__ = "Elphel"
__copyright__ = "Copyright 2018, Elphel, Inc."
__license__ = "GPL"
__version__ = "3.0+"
__maintainer__ = "Oleg K Dzhimiev"
__email__ = "oleg@elphel.com"
__status__ = "Development"

import mmap
import struct
import os
import sys
import subprocess

# globals
MEMPATH  = "/sys/devices/soc0/elphel393-mem@0"
VMEMPATH = "/sys/devices/soc0/elphel393-videomem@0"

BUF_PRE = MEMPATH+"/buffer_pages_raw_chn"

MEM_PRE    = VMEMPATH+"/membridge_start"
VFRAME_PRE = VMEMPATH+"/video_frame_number"
VMDEV_PRE  = "/dev/image_raw"
# not used here
MEMSTATUS  = VMEMPATH+"/membridge_status"

# buffer size in 4k pages
#   4096 equals 16MB
# or just calculate based on image sizes

BUF_SIZE = 4096

# sbuf - buffer in system memory = cpu RAM
# vbuf - buffer in fpga memory = video memory

def set_sbuf_size(port):
  cmd = "echo "+str(BUF_SIZE)+" > "+BUF_PRE+str(port)
  subprocess.call(cmd,shell=True)

def set_vbuf_position(port,pos):
  cmd = "echo "+str(pos)+" > "+VFRAME_PRE
  subprocess.call(cmd,shell=True)

def copy_vbuf_to_sbuf(port,frame_number):
  cmd = "echo "+str(frame_number)+" > "+MEM_PRE+str(port)
  subprocess.call(cmd,shell=True)

def save_pixel_array(port,fname):
  cmd = "cat "+VMDEV_PRE+str(port)+" > "+fname
  subprocess.call(cmd,shell=True)
  print("Memory dumped into "+fname)


# MAIN
for i in range(2):
  set_sbuf_size(i)
  set_vbuf_position(i,0)
  copy_vbuf_to_sbuf(i,0)
  save_pixel_array(i,"/tmp/port"+str(i)+".raw")

print("Now scp raw files to pc and run bayer2rgb\n")
