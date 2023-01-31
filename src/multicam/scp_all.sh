#!/bin/bash
ssh root@192.168.0.41 "sync" &
scp /home/elphel/git/elphel393/rootfs-elphel/elphel-web-393/src/multicam/multicam2.* root@192.168.0.42:/www/pages/multicam/
scp /home/elphel/git/elphel393/rootfs-elphel/elphel-web-393/src/multicam/multicam2.* root@192.168.0.43:/www/pages/multicam/
scp /home/elphel/git/elphel393/rootfs-elphel/elphel-web-393/src/multicam/multicam2.* root@192.168.0.44:/www/pages/multicam/
scp /home/elphel/git/elphel393/rootfs-elphel/elphel-web-393/src/multicam/multicam2.* root@192.168.0.45:/www/pages/multicam/

scp /home/elphel/git/elphel393/rootfs-elphel/elphel-web-393/src/diagnostics/index.html root@192.168.0.42:/www/pages/diagnostics/
scp /home/elphel/git/elphel393/rootfs-elphel/elphel-web-393/src/diagnostics/index.html root@192.168.0.43:/www/pages/diagnostics/
scp /home/elphel/git/elphel393/rootfs-elphel/elphel-web-393/src/diagnostics/index.html root@192.168.0.44:/www/pages/diagnostics/
scp /home/elphel/git/elphel393/rootfs-elphel/elphel-web-393/src/diagnostics/index.html root@192.168.0.45:/www/pages/diagnostics/

scp /home/elphel/git/elphel393/rootfs-elphel/elphel-web-393/src/diagnostics/diagnostics.css root@192.168.0.42:/www/pages/diagnostics/
scp /home/elphel/git/elphel393/rootfs-elphel/elphel-web-393/src/diagnostics/diagnostics.css root@192.168.0.43:/www/pages/diagnostics/
scp /home/elphel/git/elphel393/rootfs-elphel/elphel-web-393/src/diagnostics/diagnostics.css root@192.168.0.44:/www/pages/diagnostics/
scp /home/elphel/git/elphel393/rootfs-elphel/elphel-web-393/src/diagnostics/diagnostics.css root@192.168.0.45:/www/pages/diagnostics/

scp /home/elphel/git/elphel393/rootfs-elphel/elphel-web-393/src/diagnostics/diagnostics.js root@192.168.0.42:/www/pages/diagnostics/
scp /home/elphel/git/elphel393/rootfs-elphel/elphel-web-393/src/diagnostics/diagnostics.js root@192.168.0.43:/www/pages/diagnostics/
scp /home/elphel/git/elphel393/rootfs-elphel/elphel-web-393/src/diagnostics/diagnostics.js root@192.168.0.44:/www/pages/diagnostics/
scp /home/elphel/git/elphel393/rootfs-elphel/elphel-web-393/src/diagnostics/diagnostics.js root@192.168.0.45:/www/pages/diagnostics/


scp /home/elphel/git/elphel393/rootfs-elphel/elphel-apps-camogm/src/camogmgui/*.php root@192.168.0.42:/www/pages/
scp /home/elphel/git/elphel393/rootfs-elphel/elphel-apps-camogm/src/camogmgui/*.php root@192.168.0.43:/www/pages/
scp /home/elphel/git/elphel393/rootfs-elphel/elphel-apps-camogm/src/camogmgui/*.php root@192.168.0.44:/www/pages/
scp /home/elphel/git/elphel393/rootfs-elphel/elphel-apps-camogm/src/camogmgui/*.php root@192.168.0.45:/www/pages/

scp /home/elphel/git/elphel393/rootfs-elphel/elphel-apps-autocampars/image/usr/bin/autocampars.php root@192.168.0.42:/www/pages/
scp /home/elphel/git/elphel393/rootfs-elphel/elphel-apps-autocampars/image/usr/bin/autocampars.php root@192.168.0.43:/www/pages/
scp /home/elphel/git/elphel393/rootfs-elphel/elphel-apps-autocampars/image/usr/bin/autocampars.php root@192.168.0.44:/www/pages/
scp /home/elphel/git/elphel393/rootfs-elphel/elphel-apps-autocampars/image/usr/bin/autocampars.php root@192.168.0.45:/www/pages/


#/home/elphel/git/elphel393/rootfs-elphel/elphel-apps-camogm/src/camogmgui/

ssh root@192.168.0.41 "sync"
ssh root@192.168.0.42 "sync"
ssh root@192.168.0.43 "sync"
ssh root@192.168.0.44 "sync"
ssh root@192.168.0.45 "sync"


