DOCUMENTROOT=$(DESTDIR)/www/pages/save_single
OWN = -o root -g root
INSTDOCS = 0644

INSTALL = install
DOCS=       save_single.js \
            save_single.css

PHP_SCRIPTS= index.php

all:
	@echo "make all in src"

install:
	@echo "make install in src"
	$(INSTALL) $(OWN) -d $(DOCUMENTROOT)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(DOCS) $(DOCUMENTROOT)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(PHP_SCRIPTS) $(DOCUMENTROOT)

clean:
	@echo "make clean in src"
