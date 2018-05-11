DOCUMENTROOT=$(DESTDIR)/www/pages
OWN = -o root -g root
INSTDOCS = 0644
INSTALL = install
DOCS=       index.html \
            diagnostics.js \
            diagnostics.css

PHP_SCRIPTS= diagnostics.php

all:
	@echo "make all in src"

install:
	@echo "make install in src"
	$(INSTALL) $(OWN) -d $(DOCUMENTROOT) $(DOCUMENTROOT)/diagnostics
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(DOCS) $(DOCUMENTROOT)/diagnostics
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(PHP_SCRIPTS) $(DOCUMENTROOT)

clean:
	@echo "make clean in src"
