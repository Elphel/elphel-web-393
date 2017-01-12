DOCUMENTROOT=$(DESTDIR)/www/pages
OWN = -o root -g root
INSTDOCS = 0644
INSTALL = install
DOCS=       jp4-canvas.html \
            jp4-canvas.js 

LIBS=       elphel.js \
            exif.js \
            jquery-jp4.js \
            jcanvas.js
            
PHP_SCRIPTS=get-image.php

all:
	@echo "make all in src"

install:
	@echo "make install in src"
	$(INSTALL) $(OWN) -d $(DOCUMENTROOT) $(DOCUMENTROOT)/js
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(DOCS) $(DOCUMENTROOT)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(LIBS) $(DOCUMENTROOT)/js
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(PHP_SCRIPTS) $(DOCUMENTROOT)

clean:
	@echo "make clean in src"
