PYTHONROOT=$(DESTDIR)/usr/local/bin
OWN = -o root -g root
INSTPYTHON = 0755
#INSTALL = install
PYTHON_SCRIPTS = tests.py

all:
	@echo "make all in python_tests"

install:
	@echo "make install in python_tests"
	$(INSTALL) $(OWN) -d $(PYTHONROOT)
	$(INSTALL) $(OWN) -m $(INSTPYTHON) $(PYTHON_SCRIPTS) $(PYTHONROOT)
clean:
	@echo "make clean in src"
