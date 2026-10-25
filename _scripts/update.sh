#!/bin/sh
# install the latest release binary on the server

. ./_scripts/common.sh

if _install; then
	_restart
fi

_info 'Done'
exit 0
