DOCUMENTROOT=$(DESTDIR)/www/pages/pointers
OWN = -o root -g root
INSTDOCS = 0644

INSTALL = install
DOCS=       index.html \
            index.js \
            index.css

PHP_SCRIPTS= pointers.php \
             test.php

all:
	@echo "make all in src"

install:
	@echo "make install in src"
	$(INSTALL) $(OWN) -d $(DOCUMENTROOT)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(DOCS) $(DOCUMENTROOT)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(PHP_SCRIPTS) $(DOCUMENTROOT)

clean:
	@echo "make clean in src"
