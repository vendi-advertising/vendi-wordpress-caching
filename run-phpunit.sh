#!/bin/bash

##see: http://stackoverflow.com/questions/192249/how-do-i-parse-command-line-arguments-in-bash
while [[ $# -gt 1 ]]
do
key="$1"

case $key in
    -g|--group)
    GROUP="$2"
    shift # past argument
    ;;
    *)
            # unknown option
    ;;
esac
shift # past argument or value
done

if [ -z "$GROUP" ]; then
    phpunit --coverage-html ./tests/logs/coverage/
else
    phpunit --coverage-html ./tests/logs/coverage/ --group $GROUP
fi


#phpunit --coverage-html ./tests/logs/coverage/

