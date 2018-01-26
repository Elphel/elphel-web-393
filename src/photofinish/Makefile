DOCUMENTROOT=$(DESTDIR)/www/pages/photofinish
OWN = -o root -g root
INSTDOCS = 0644
INSTALL = install
DOCS=       index.html \
            photo-finish.js \
            photo-finish.php

all:
	@echo "make all in src"

install:
	@echo "make install in src"
	$(INSTALL) $(OWN) -d $(DOCUMENTROOT)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(DOCS) $(DOCUMENTROOT)

clean:
	@echo "make clean in src"
