#!/bin/sh 
  
# notify the upload path 
NOTIFYPATH='/home/webroot/upload/'`date +%y%m%d` 
#cd $NOTIFYPATH 

inotifywait -mr --timefmt '%d/%m/%y %H:%M' --format '%T %w %f' -e close_write,move $NOTIFYPATH | while read date time dir FILE; do
  
    FILECHANGE=${dir}${FILE} 
    # convert absolute path to relative 
    #FILECHANGEREL=`echo "$FILECHANGE" | sed 's_'$CURPATH'/__'` 
  
    echo $FILE | egrep "(db$|db3$|journal$|log$|^\\.)"
    if [ $? -eq 0 ]; then
        continue
    fi
    /usr/local/webserver/php/bin/php /home/webroot/bdSync/main.php -u $FILECHANGE ${NOTIFYPATH}  
done