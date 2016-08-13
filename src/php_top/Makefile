DOCUMENTROOT=$(DESTDIR)/www/pages
OWN = -o root -g root
INSTDOCS = 0644
INSTALL = install
DOCS=       favicon.ico \
            robots.txt

PHP_SCRIPTS=camvars.php \
            ccam.php \
            diag_utils.php \
            framepars.php \
            parsedit.php \
            phpinfo.php

all:
	@echo "make all in src"

install:
	@echo "make install in src"
	$(INSTALL) $(OWN) -d $(DOCUMENTROOT)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(DOCS) $(DOCUMENTROOT)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(PHP_SCRIPTS) $(DOCUMENTROOT)
clean:
	@echo "make clean in src"
