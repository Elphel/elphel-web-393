DOCUMENTROOT=$(DESTDIR)/www/pages/lwir16
OWN = -o root -g root
INSTDOCS = 0644
INSTEXE =  0755

SYSCONFDIR   =/etc
CONFDIR      = $(SYSCONFDIR)/elphel393


INSTALL = install
DOCS=       index.html
            
PHP_SCRIPTS_EXE=lwir16.php \
            test_int.php \
            test_wpipe.php \
            test_ps.php
CONFIGS = lwir16.ini            
            
all:
	@echo "make all in src"

install:
	@echo "make install in src"
	$(INSTALL) $(OWN) -d $(DOCUMENTROOT)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(DOCS) $(DOCUMENTROOT)
	$(INSTALL) $(OWN) -m $(INSTEXE)  $(PHP_SCRIPTS_EXE) $(DOCUMENTROOT)
	$(INSTALL) -d $(DESTDIR)$(SYSCONFDIR)
	$(INSTALL) -d $(DESTDIR)$(CONFDIR)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(CONFIGS) $(DESTDIR)$(CONFDIR)

clean:
	@echo "make clean in src"
