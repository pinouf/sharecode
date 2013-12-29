install

---------------------------

install des vendors

    php composer.phar install

configurer les droits

    rm -rf app/cache/*
    rm -rf app/logs/*
    sudo chmod +a "tonuser allow delete,write,append,file_inherit,directory_inherit" app/cache app/logs
    sudo chmod +a "_www allow delete,write,append,file_inherit,directory_inherit" app/cache app/logs
    sudo chmod +a "daemon allow delete,write,append,file_inherit,directory_inherit" app/cache app/logs
