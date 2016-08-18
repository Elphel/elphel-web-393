DOCUMENTROOT=$(DESTDIR)/www/pages
OWN = -o root -g root
INSTDOCS = 0644
INSTALL = install
DOCS=       debugfs.html \
            debugfs.css \
            debugfs.js

PHP_SCRIPTS=debugfs.php

all:
	@echo "make all in src"

install:
	@echo "make install in src"
	$(INSTALL) $(OWN) -d $(DOCUMENTROOT)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(DOCS) $(DOCUMENTROOT)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(PHP_SCRIPTS) $(DOCUMENTROOT)

clean:
	@echo "make clean in src"
