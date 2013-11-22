bdSync
======

a file synchronization program based on baidu netdisk and inotify-tools

####Getting and install inotify-tools

#####Fedora/Cent os

inotify-tools is available through the Fedora Extras repository. Just do:

    yum install inotify-tools

#####Debian/Ubuntu

inotify-tools is available in Debian’s official repositories. You can install it by:

    apt-get install inotify-tools

#####Other Linux
 you can also find more infomation from here: <https://github.com/rvoicilas/inotify-tools/wiki>
 
 
####usage
1. Copy file config.sample.php to config.php
2. Execute the command line:`/usr/bin/php ./main.php -init`
       
      >notice：
      use `which php` to find where is the php installed

3. Use browser to get access_token by what you should to do
4. Modify notify.sh to monitor the dir what you want to sync to your baidu netdisk and use this command
    	
    	nohup ./notify.sh & 
5. for more infomation ,type this command:

		php ./main.php -h
    	