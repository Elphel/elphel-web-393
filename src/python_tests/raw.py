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
import time
import xml.etree.ElementTree as ET

import elphel_framepars as EF

MMAP_DATA = True
#MMAP_DATA = False

# globals
VPATH = "/sys/devices/soc0/elphel393-videomem@0"

MEM_PRE     = VPATH+"/membridge_start"
VFRAME_PRE  = VPATH+"/video_frame_number"
RAWINFO_PRE = VPATH+"/raw_frame_info"

# not used here
MEMSTATUS  = VPATH+"/membridge_status"

#frameparsPaths = ("/dev/frameparsall0","/dev/frameparsall1","/dev/frameparsall2","/dev/frameparsall3")

BUF_SIZE = 4096
PAGE_SIZE = 4096

# sbuf - buffer in system memory = cpu RAM
# vbuf - buffer in fpga memory = video memory

# buffer size in 4k pages
#   4096 equals 16MB
# or recalculate based on image dimensions
def set_sbuf_size(port,size):
  cmd = "echo "+str(size)+" > /sys/devices/soc0/elphel393-mem@0/buffer_pages_raw_chn"+str(port)
  subprocess.call(cmd,shell=True)

def set_vbuf_position(port,pos):
  cmd = "echo "+str(pos)+" > "+VFRAME_PRE
  subprocess.call(cmd,shell=True)

def copy_vbuf_to_sbuf(port,frame_number):
  cmd = "echo "+str(frame_number)+" > "+MEM_PRE+str(port)
  subprocess.call(cmd,shell=True)

def save_pixel_array(port,fname):
  cmd = "cat /dev/image_raw"+str(port)+" > "+fname
  subprocess.call(cmd,shell=True)
  print("Memory dumped into "+fname)

def mmap_pixel_array(port,size):
  with open("/dev/image_raw"+str(port),"r+b") as fp:
      data = mmap.mmap(fp.fileno(), size, offset = 0) # mmap all data
  return data

def get_byte(data,i):
  return struct.unpack_from(">B",data[i])[0]

def get_byte_str(data,i):
  res = get_byte(data,i)
  #return str(hex(res))
  return "{:02x}".format(res)
  #return format(get_byte(data,i),'#02x')

# normally meta is 1 frame behind but can be read after
# membridge transfer is done
def get_timestamp_from_meta(port,frame_number):
  tmpfile = "/tmp/meta.xml"
  cmd = "wget -qO- 'http://127.0.0.1:"+str(IMGSRV_BASE_PORT+port)+"/meta' > "+tmpfile
  subprocess.call(cmd,shell=True)

  e = ET.parse(tmpfile).getroot()

  for i in e.iter('DateTimeOriginal'):
    #run once
    ts = i.text.strip("\"")
    break

  for i in e.iter('ImageNumber'):
    #run once
    inf = int(i.text.strip("\""))
    break

  for i in e.iter('currentSensorFrame'):
    #run once
    csf = int(i.text.strip("\""))
    break

  print("REF: "+str(frame_number)+"    ImageNumber: "+str(inf)+"    currentSensorFrame:"+str(csf))

  os.remove(tmpfile)

  return ts

# MAIN

IMGSRV_BASE_PORT = 2323
mmap_data = []
sensors = []

# First detect how many sensor ports are there
for i in range(4):
  path = "/sys/devices/soc0/elphel393-detect_sensors@0/sensor"+str(i)+"0"
  if os.path.isfile(path):
    with open(path) as f:
      if (f.read().strip()!="none"):
        sensors.append(i)

print("Available sensors: "+str(sensors))


# Now get which one is the master (from the 1st available sensor)
p = EF.Pars("/dev/frameparsall"+str(sensors[0]))
tmp_frame_num = p.get_frame_number()
trig_master = p.value(p.P_TRIG_MASTER,tmp_frame_num)
trig_master_port = IMGSRV_BASE_PORT+int(trig_master)
trig_period = p.value(p.P_TRIG_PERIOD,tmp_frame_num)
print("TRIG MASTER = "+str(trig_master))
print("TRIG PERIOD = "+str(trig_period)+" us")


# Stop master trigger (not needed)
#cmd = "wget -qO- 'http://127.0.0.1:"+str(trig_master_port)+"/trig/pointers' &> /dev/null"
#print("Stop trigger:")
#subprocess.call(cmd,shell=True)

#
# First set the buffer size and mmap
#
for i in sensors:
  size = BUF_SIZE
  set_sbuf_size(i,size)
  if MMAP_DATA:
    mmap_data.append(mmap_pixel_array(i,BUF_SIZE*PAGE_SIZE))

#
# Get pixel data to from fpga(=video) memory to system memory
#
for i in sensors:

  # Master port (if not overriden)
  p = EF.Pars("/dev/frameparsall"+str(i))

  print("Port "+str(i)+":")

  #frame_num = p.get_frame_number()+1
  #print("    frame number: "+str(frame_num))

  set_vbuf_position(i,0)
  # waiting for frame is built-in in the driver
  copy_vbuf_to_sbuf(i,tmp_frame_num+1)

  # get timestamp
  #ts = get_timestamp_from_meta(i,0)
  #print("    timestamp: "+ts)

  if MMAP_DATA:
    # print the first 16 bytes for test purposes
    print("test output: " +" ".join("{:02x}".format(get_byte(mmap_data[i],c)) for c in range(16)))
    # test: hexdump -C /dev/image_raw0
  else:
    save_pixel_array(i,"/tmp/port"+str(i)+".raw")


# Restore trigger
#print("Restore trigger")
#cmd = "wget -qO- 'http://127.0.0.1/parsedit.php?sensor_port="+str(trig_master)+"&immediate&TRIG_PERIOD="+str(trig_period)+"*1' &> /dev/null"
#subprocess.call(cmd,shell=True)


# debug info (might be useful for some standard raw format headers)
print("Debug, stored raw frames parameters:")
# read raw frames parameters
for i in sensors:
  with open(RAWINFO_PRE+str(i)) as f:
    print("Port "+str(i)+": ")
    # insert 4 spaces before each line of text
    print("\n".join("    "+c for c in f.read().strip().split("\n")))



if not MMAP_DATA:
  print("Now scp raw files to pc and run bayer2rgb")















