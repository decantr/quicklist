#!/bin/sh
# update the project on the server

. ./_scripts/common.sh

# get latest version
git pull origin master || _error "Pull failed"

_build
_restart

_info 'Done'
exit 0
