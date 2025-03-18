#!/bin/sh

if [[ "$(uname -s)" == Darwin* ]]; then
cd "$( dirname "$0" )"
else
cd "$(dirname "$(realpath "$0")")";
fi

if [[ $(uname -p) == "arm" ]]; then
	tag="6.x-arm64"
else
	tag="6.x"
fi

docker run -v $PWD:/app --rm -it ghcr.io/danog/psalm:$tag /composer/vendor/bin/psalm --no-cache \
--alter --issues=MissingReturnType,UnusedVariable,ClassMustBeFinal,MissingParamType,UnusedMethod,PossiblyUnusedMethod
