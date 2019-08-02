#release

##generate phpdoc 

docker run -it --rm -v $(pwd):/data phpdoc/phpdoc --cache-folder /tmp -d src -t phpdoc -i src/core/log4php

##release and publish

TODO