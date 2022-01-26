DOCUMENTROOT=$(DESTDIR)/www/pages/multicam
DOCUMENTROOTZIP=$(DESTDIR)/www/pages/js/zip
OWN = -o root -g root
INSTDOCS = 0644
INSTALL = install
DOCS=       index.html \
			index1.html \
            multicam.js \
            multicam.css \
            multicam.php

ZIPDOCS= zip/deflate.js \
         zip/inflate.js \
         zip/z-worker.js \
         zip/zip.js

all:
	@echo "make all in src"

install:
	@echo "make install in src"
	$(INSTALL) $(OWN) -d $(DOCUMENTROOT)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(DOCS) $(DOCUMENTROOT)
	$(INSTALL) $(OWN) -d $(DOCUMENTROOTZIP)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(ZIPDOCS) $(DOCUMENTROOTZIP)

clean:
	@echo "make clean in src"
