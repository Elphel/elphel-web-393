
# Read frames' parameters. For Elphel NC393 cameras

__copyright__ = "Copyright 2018, Elphel, Inc."
__license__   = "GPL-3.0+"
__email__     = "oleg@elphel.com"

import mmap
import struct

class Pars:

  # parameters
  P_WOI_WIDTH  = 26
  P_WOI_HEIGHT = 27
  # ...
  P_FRAME      = 136

  # constants
  ## frame params queue length
  MAX_FRAMES = 16
  ## 4 bytes per param
  BYTE_MODE  = 4
  ## params per frame from queue
  PARS_SIZE   = 1024
  ## all mem
  MMAP_SIZE  = MAX_FRAMES*BYTE_MODE*PARS_SIZE # bytes

  ENDIAN = "<" # little, ">" for big
  FRMT_BYTES = {1:'B',2:'H',4:'L',8:'Q'}
  FMT = ENDIAN+FRMT_BYTES[BYTE_MODE]

  def __init__(self,filename=""):
    with open(filename,"r+b") as fp:
      self.data = mmap.mmap(fp.fileno(), self.MMAP_SIZE, offset = 0) # mmap all data

  def value(self,param,frame):

    offset  = (frame%self.MAX_FRAMES)*self.PARS_SIZE*self.BYTE_MODE
    offset += (param%self.PARS_SIZE)*self.BYTE_MODE

    data = self.data[offset:offset+self.BYTE_MODE]

    res = struct.unpack_from(self.FMT,data)[0]

    return res

#MAIN
if __name__ == "__main__":
  print("Test")
  a = Pars('/dev/frameparsall0')
  print("  port 0, frame #:      "+format(a.value(Pars.P_FRAME,0),'#08x'))
  print("  port 0, frame width:  "+format(a.value(Pars.P_WOI_WIDTH,0),'#08x'))
  print("  port 0, frame height: "+format(a.value(Pars.P_WOI_HEIGHT,0),'#08x'))
