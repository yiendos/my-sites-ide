#!/usr/bin/env bash

#maybe default port needs to be set
#https://medium.com/@kharysharpe/automatic-local-domains-setting-up-dnsmasq-for-macos-high-sierra-using-homebrew-caf767157e43

#also how to ensure launched at restart
#https://passingcuriosity.com/2013/dnsmasq-dev-osx/
#sudo cp $(brew list dnsmasq | grep /homebrew.mxcl.dnsmasq.plist$) /Library/LaunchDaemons/

#verify changes
#scutil --dns

#although even then, maybe a restart required

#set our flag initial to false;
update=false;

## is brew installed
if ! command -v brew > /dev/null; then
  echo "* brew is not installed";
  exit 1;
fi

#firstly is dnsmasq installed
if ! command -v dnsmasq > /dev/null; then
  brew update;
  brew install dnsmasq;
fi

#check to see whether dnsmasq is configured
FILE=/usr/local/etc/dnsmasq.conf;
LINE="conf-dir=/usr/local/etc/dnsmasq.d,*.conf";

if ! echo "$OUTPUT" | grep -qF -- "$LINE" "$FILE"; then
  update=true;
  echo $LINE | sudo tee -a $FILE;
fi

#have we created our override directory
if [ ! -d /usr/local/etc/dnsmasq.d ]; then
  update=true;
  mkdir -p /usr/local/etc/dnsmasq.d;
fi

#have we created our override
if [ ! -f /usr/local/etc/dnsmasq.d/local.conf ]; then
  update=true;
  LINE="address=/.local/127.0.0.1";
  touch /usr/local/etc/dnsmasq.d/local.conf;
  echo $LINE | sudo tee -a /usr/local/etc/dnsmasq.d/local.conf;
fi

#be sure to update resolver too
if [ ! -d /etc/resolver ]; then
  update=true;
  sudo mkdir -p /etc/resolver;
fi

#create our local resolver
if [ ! -f /etc/resolver/local ]; then
  update=true;
  LINE="nameserver 127.0.0.1";
  sudo echo $LINE | sudo tee -a /etc/resolver/local;
fi

#was anything missing, then run an update
if $update ; then
  brew services restart dnsmasq
  #sudo killall -HUP mDNSResponder
fi