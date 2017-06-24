DOCUMENTROOT=$(DESTDIR)/www/pages/jp4-viewer
OWN = -o root -g root
INSTDOCS = 0644
INSTALL = install
DOCS=       index.html \
            jp4-viewer.js \
            jp4-viewer.css
            
all:
	@echo "make all in src"

install:
	@echo "make install in src"
	$(INSTALL) $(OWN) -d $(DOCUMENTROOT)
	$(INSTALL) $(OWN) -m $(INSTDOCS) $(DOCS) $(DOCUMENTROOT)

clean:
	@echo "make clean in src"
