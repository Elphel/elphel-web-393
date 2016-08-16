#!/usr/bin/env python
from __future__ import division
from __future__ import print_function

import mmap
import struct
import os
import sys

#fp = open("/dev/frameparsall0","w+")
frameparsPaths = ("/dev/frameparsall0","/dev/frameparsall1","/dev/frameparsall2","/dev/frameparsall3")
mmap_size= 85*1024*4 # bytes
ENDIAN="<" # little, ">" for big
FRMT_BYTES={1:'B',2:'H',4:'L',8:'Q'}

def test_mmap (sensor_port = 0, out_path_base="mmap_dump_port"):
    with open(out_path_base+str(sensor_port), "w+b") as bf:
        with open(frameparsPaths[sensor_port],"w+") as fp:
            mm = mmap.mmap(fp.fileno(), mmap_size, offset = 0) # mmap all data
            bf.write(mm)
             
#            byte_mode = 4
#           data=struct.unpack_from(ENDIAN+FRMT_BYTES[byte_mode],mm, page_offs)

"""
        elphel_globals->frameParsAll[port] = (struct framepars_all_t *) mmap(0, sizeof (struct framepars_all_t) , PROT_READ | PROT_WRITE, MAP_SHARED, elphel_globals->fd_fparmsall[port], 0);
/// All parameter data for a sensor port, including future ones and past. Size should be PAGE_SIZE aligned
struct framepars_all_t {
    struct framepars_t      framePars[PARS_FRAMES=16];                ///< Future frame parameters
    struct framepars_t      func2call;                             ///< func2call.pars[] - each parameter has a 32-bit mask of what pgm_function to call - other fields not used
     unsigned long          globalPars[NUM_GPAR=2048];                  ///< parameters that are not frame-related, their changes do not initiate any actions so they can be mmaped for both R/W
    struct framepars_past_t pastPars [PASTPARS_SAVE_ENTRIES= (16 << 7)=2048];      ///< parameters of previously acquired frames
     unsigned long          multiSensIndex[P_MAX_PAR_ROUNDUP = 1024];     ///< indexes of individual sensor register shadows (first of 3) - now for all parameters, not just sensor ones
     unsigned long          multiSensRvrsIndex[P_MAX_PAR_ROUNDUP = 1024]; ///< reverse index (to parent) for the multiSensIndex in lower 16 bits, high 16 bits - sensor number
};

struct framepars_t { 1024
        unsigned long pars[927];      ///< parameter values (indexed by P_* constants)
        unsigned long functions;      ///< each bit specifies function to be executed (triggered by some parameters change)
        unsigned long modsince[31];   ///< parameters modified after this frame - each bit corresponds to one element in in par[960] (bit 31 is not used)
        unsigned long modsince32;     ///< parameters modified after this frame super index - non-zero elements in in mod[31]  (bit 31 is not used)
        unsigned long mod[31];        ///< modified parameters - each bit corresponds to one element in in par[960] (bit 31 is not used)
        unsigned long mod32;          ///< super index - non-zero elements in in mod[31]  (bit 31 is not used)
        unsigned long needproc[31];   ///< FIXME: REMOVE parameters "modified and not yet processed" (some do not need any processing)
        unsigned long needproc32;     ///< FIXME: REMOVE parameters "modified and not yet processed" frame super index - non-zero elements in in mod[31]  (bit 31 is not used)
};

0x00000 1024*16    struct framepars_t      framePars[PARS_FRAMES=16];                ///< Future frame parameters
0x04000 1024       struct framepars_t      func2call;                             ///< func2call.pars[] - each parameter has a 32-bit mask of what pgm_function to call - other fields not used
0x04400 2048       unsigned long          globalPars[NUM_GPAR=2048];                  ///< parameters that are not frame-related, their changes do not initiate any actions so they can be mmaped for both R/W
0x04c00 32*2048    struct framepars_past_t pastPars [PASTPARS_SAVE_ENTRIES= (16 << 7)=2048];      ///< parameters of previously acquired frames
0x14c00 1024       unsigned long          multiSensIndex[P_MAX_PAR_ROUNDUP = 1024];     ///< indexes of individual sensor register shadows (first of 3) - now for all parameters, not just sensor ones
0x15000 1024       unsigned long          multiSensRvrsIndex[P_MAX_PAR_ROUNDUP = 1024]; ///< reverse index (to parent) for the multiSensIndex in lower 16 bits, high 16 bits - sensor number
0x15400
         85*1024*4 for mmap
return mmap.mmap(f.fileno(), self.PAGE_SIZE, offset = page_addr)

            with open("/dev/mem", "r+b") as f:
                for addr in range (start_addr,end_addr+byte_mode,byte_mode):
                    page_addr=addr & (~(self.PAGE_SIZE-1))
                    page_offs=addr-page_addr
                    mm = self.wrap_mm(f, page_addr)
        #            if (page_addr>=0x80000000):
        #                page_addr-= (1<<32)
        #            mm = mmap.mmap(f.fileno(), self.PAGE_SIZE, offset=page_addr)
                    data=struct.unpack_from(self.ENDIAN+frmt_bytes[byte_mode],mm, page_offs)
                    rslt.append(data[0])
                    
        for addr in range (start_addr,end_addr+byte_mode,byte_mode):
            if (addr == start_addr) or ((addr & print_mask) == 0):
                if self.DRY_MODE:
                    print ("\nsimulated: 0x%08x:"%addr,end="")
                else:     
                    print ("\n0x%08x:"%addr,end="")
            d=rslt[(addr-start_addr) // byte_mode]
            print (data_frmt%(d),end=" ")
        print("")
        return rslt    

                mm = self.wrap_mm(f, page_addr)
    #            if (page_addr>=0x80000000):
    #                page_addr-= (1<<32)
    #            mm = mmap.mmap(f.fileno(), self.PAGE_SIZE, offset=page_addr)
                bf.write(mm[start_offset:end_offset])


"""
if __name__ == "__main__":
    test_mmap(0)
