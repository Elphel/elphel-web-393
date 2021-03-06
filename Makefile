# Runs 'make', 'make install', and 'make clean' in specified subdirectories
SUBDIRS := src/php_top src/python_tests src/debugfs-webgui src/jp4-canvas src/update src/eyesis4pi src/index src/pointers src/snapshot src/jp4-viewer src/photofinish src/multicam src/diagnostics # src1
INSTALLDIRS = $(SUBDIRS:%=install-%)
CLEANDIRS =   $(SUBDIRS:%=clean-%)

#TARGETDIR=$(DESTDIR)/www/pages

all: $(SUBDIRS)
	@echo "make all top"

$(SUBDIRS):
	$(MAKE) -C $@

install: $(INSTALLDIRS)
	echo "make install top"

$(INSTALLDIRS):
	$(MAKE) -C $(@:install-%=%) install

clean: $(CLEANDIRS)
	@echo "make clean top"

$(CLEANDIRS):
	$(MAKE) -C $(@:clean-%=%) clean

.PHONY: all install clean $(SUBDIRS) $(INSTALLDIRS) $(CLEANDIRS)

