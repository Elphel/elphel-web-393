DOCUMENTROOT=www/pages
OWN = -o root -g root
INSTDOCS = 0644
INSTALL = install
DOCS=       favicon.ico \
            robots.txt
CONFS=      autocampars.xml            
SYSCONFDIR   = etc
CONFDIR      = $(SYSCONFDIR)/elphel393

PHP_SCRIPTS=camvars.php \
            ccam.php \
            diag_utils.php \
            framepars.php \
            parsedit.php \
            phpinfo.php

all:
	@echo "make all in src"
	@echo "REMOTE_IP =$(REMOTE_IP)"
	@echo "REMOTE_USER =$(REMOTE_USER)"

install:
	@echo "make install in src"
	$(INSTALL) $(OWN) -d $(DESTDIR)/$(DOCUMENTROOT)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(DOCS) $(DESTDIR)/$(DOCUMENTROOT)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(PHP_SCRIPTS) $(DESTDIR)/$(DOCUMENTROOT)
	$(INSTALL) $(OWN) -d $(DESTDIR)/$(SYSCONFDIR)
	$(INSTALL) $(OWN) -d $(DESTDIR)/$(CONFDIR)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(CONFS) $(DESTDIR)/$(CONFDIR)
clean:
	@echo "make clean in src"
