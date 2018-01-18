
# Read frames' parameters. For Elphel NC393 cameras

__copyright__ = "Copyright 2018, Elphel, Inc."
__license__   = "GPL-3.0+"
__email__     = "oleg@elphel.com"

import mmap
import struct

# MAP
"""
0x00000 1024*16 struct framepars_t framePars[PARS_FRAMES=16]; ///< Future frame parameters

0x04000 1024    struct framepars_t func2call;                 ///< func2call.pars[]
                                                              ///  each parameter has a 32-bit mask of what
                                                              ///  pgm_function to call other fields not used

0x04400 2048    unsigned long globalPars[NUM_GPAR=2048];      ///< parameters that are not frame-related, their
                                                              ///  changes do not initiate any actions so they can
                                                              ///  be mmaped for both R/W

0x04c00 32*2048 struct framepars_past_t pastPars[PASTPARS_SAVE_ENTRIES=(16 << 7)=2048]; ///< parameters of previously acquired frames

0x14c00 1024    unsigned long multiSensIndex[P_MAX_PAR_ROUNDUP=1024]; ///< indexes of individual sensor register shadows
                                                                      ///  (first of 3) - now for all parameters, not just sensor ones

0x15000 1024    unsigned long multiSensRvrsIndex[P_MAX_PAR_ROUNDUP = 1024]; ///< reverse index (to parent) for the multiSensIndex
                                                                            ///  in lower 16 bits, high 16 bits - sensor number
0x15400
         85*1024*4 for mmap
"""

class Pars:

  # parameters
  P_TRIG_MASTER = 3
  # ...
  P_WOI_WIDTH  = 26
  P_WOI_HEIGHT = 27
  # ...
  P_TRIG_PERIOD = 105
  # ...
  P_FRAME      = 136

  P_FRAME_PAST = 8

  # constants

  ## frame params queue length
  MAX_FRAMES      = 16
  MAX_FRAMES_PAST = 512
  ## 4 bytes per param
  BYTE_MODE       = 4
  ## params per frame from queue

  PARS_SIZE      = 1024
  PARS_SIZE_PAST = 32
  ## all mem

  PARS_OFFSET = 0x0
  PARS_OFFSET_PAST = 0x04c00*BYTE_MODE

  #MMAP_SIZE  = MAX_FRAMES*BYTE_MODE*PARS_SIZE # bytes
  #MMAP_SIZE_PAST  = MAX_FRAMES*BYTE_MODE*PARS_SIZE # bytes
  MMAP_SIZE_ALL  = 85*1024*4

  ENDIAN = "<" # little, ">" for big
  FRMT_BYTES = {1:'B',2:'H',4:'L',8:'Q'}
  FMT = ENDIAN+FRMT_BYTES[BYTE_MODE]

  #
  def __init__(self,filename=""):

    # extract port
    self.port = filename[len("/dev/frameparsall"):]

    with open(filename,"r+b") as fp:
      self.data      = mmap.mmap(fp.fileno(), self.MMAP_SIZE_ALL, offset = self.PARS_OFFSET) # mmap some data
      #self.data_past = mmap.mmap(fp.fileno(), self.MMAP_SIZE_PAST, offset = self.PARS_OFFSET_PAST) # mmap some data

  #
  def value(self,param,frame):

    offset  = (frame%self.MAX_FRAMES)*self.PARS_SIZE*self.BYTE_MODE
    offset += (param%self.PARS_SIZE)*self.BYTE_MODE

    data = self.data[offset:offset+self.BYTE_MODE]

    res = struct.unpack_from(self.FMT,data)[0]

    return res

  #
  def value_past(self,param,frame):

    offset  = self.PARS_OFFSET_PAST
    offset += (frame%self.MAX_FRAMES_PAST)*self.PARS_SIZE_PAST*self.BYTE_MODE
    offset += param*self.BYTE_MODE

    data = self.data[offset:offset+self.BYTE_MODE]

    res = struct.unpack_from(self.FMT,data)[0]

    return res

  def get_frame_number(self):
    with open("/sys/devices/soc0/elphel393-framepars@0/this_frame"+str(self.port)) as file:
      data = file.read()

    return int(data)


#MAIN
if __name__ == "__main__":

  print("Test")

  a = Pars('/dev/frameparsall0')

  frame = a.get_frame_number()
  frame = frame - 1
  print("OLD FRAME NUMBER: "+"0x{:08x}".format(frame))

  # read future pars
  print("  port 0, frame #:      "+"0x{:08x}".format(a.value(Pars.P_FRAME,frame&0xf)))
  print("  port 0, frame width:  "+"0x{:08x}".format(a.value(Pars.P_WOI_WIDTH,frame&0xf)))
  print("  port 0, frame height: "+"0x{:08x}".format(a.value(Pars.P_WOI_HEIGHT,frame&0xf)))

  print("  port 0, past frame number: "+"0x{:08x}".format(a.value_past(Pars.P_FRAME_PAST,frame&0x1ff)))
  print("  port 0, past frame number: "+"0x{:08x}".format(a.value_past(Pars.P_FRAME_PAST,(frame-1)&0x1ff)))
  print("  port 0, past frame number: "+"0x{:08x}".format(a.value_past(Pars.P_FRAME_PAST,(frame-2)&0x1ff)))





















