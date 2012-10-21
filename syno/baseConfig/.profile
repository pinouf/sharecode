umask 022

PATH=/opt/bin:/opt/sbin:/sbin:/bin:/usr/sbin:/usr/bin:/usr/syno/sbin:/usr/syno/bin:/usr/local/sbin:/usr/local/bin
export PATH

#This fixes the backspace when telnetting in.
#if [ "$TERM" != "linux" ]; then
#        stty erase
#fi

HOME=/root
export HOME

TERM=${TERM:-cons25}
export TERM

PAGER=more
export PAGER


# set a fancy prompt (non-color, unless we know we "want" color)
PS1="[]-[]-[] \[\e[01;32m\]\u@\h\[\e[00m\]:\[\e[01;34m\]\w\[\e[00m\]\$ "

alias dir="ls -al"
alias ll="ls -la"

# color bash
export CLICOLOR=1
export LSCOLORS=GxFxCxDxBxegedabagaced

# editor par defaut
export EDITOR=vim¤
