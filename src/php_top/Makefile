DOCUMENTROOT=/www/pages
INCLUDES=$(DOCUMENTROOT)/include
OWN = -o root -g root
INSTDOCS = 0644
#INSTALL = install
DOCS=       favicon.ico \
            robots.txt
#CONFS=      autocampars.xml            
SYSCONFDIR   = /etc
CONFDIR      = $(SYSCONFDIR)/elphel393

PHP_SCRIPTS=i2c.php \
            camvars.php \
            ccam.php \
            diag_utils.php \
            framepars.php \
            parsedit.php \
            phpinfo.php \
            snapfull.php \
            tuneseq.php

PHPINCLUDES=i2c_include.php \
            show_source_include.php \
            elphel_functions_include.php
            
all:
	@echo "make all in src"
	@echo "REMOTE_IP =$(REMOTE_IP)"
	@echo "REMOTE_USER =$(REMOTE_USER)"

install:
	@echo "make install in src"
	$(INSTALL) $(OWN) -d $(DESTDIR)$(DOCUMENTROOT)
	$(INSTALL) $(OWN) -d $(DESTDIR)$(INCLUDES)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(DOCS) $(DESTDIR)$(DOCUMENTROOT)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(PHP_SCRIPTS) $(DESTDIR)$(DOCUMENTROOT)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(PHPINCLUDES) $(DESTDIR)$(INCLUDES)
	$(INSTALL) $(OWN) -d $(DESTDIR)$(SYSCONFDIR)
	$(INSTALL) $(OWN) -d $(DESTDIR)$(CONFDIR)

#	$(INSTALL) $(OWN) -m $(INSTDOCS) -T autocampars.xml $(DESTDIR)$(CONFDIR)/autocampars0.xml
#	$(INSTALL) $(OWN) -m $(INSTDOCS) -T autocampars.xml $(DESTDIR)$(CONFDIR)/autocampars1.xml
#	$(INSTALL) $(OWN) -m $(INSTDOCS) -T autocampars.xml $(DESTDIR)$(CONFDIR)/autocampars2.xml
#	$(INSTALL) $(OWN) -m $(INSTDOCS) -T autocampars.xml $(DESTDIR)$(CONFDIR)/autocampars3.xml
clean:
	@echo "make clean in src"
