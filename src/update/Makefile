DOCUMENTROOT=$(DESTDIR)/www/pages
OWN = -o root -g root
INSTDOCS = 0644
INSTALL = install

DOCS= update_nand.php \
           update_software.html \
           update_software.js \
           update_software.php

LIB_DIR= fileupload
           
LIBS=      $(LIB_DIR)/jquery.fileupload.css \
           $(LIB_DIR)/jquery.fileupload.js \
           $(LIB_DIR)/jquery.iframe-transport.js \
           $(LIB_DIR)/style.css \
           $(LIB_DIR)/UploadHandler.php

all:
	@echo "make all in src"

install:
	@echo "make install in src"
	$(INSTALL) $(OWN) -d $(DOCUMENTROOT) $(DOCUMENTROOT)/js $(DOCUMENTROOT)/js/$(LIB_DIR)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(DOCS) $(DOCUMENTROOT)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(LIBS) $(DOCUMENTROOT)/js/$(LIB_DIR)

clean:
	@echo "make clean in src"
