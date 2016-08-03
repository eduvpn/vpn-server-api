#!/bin/sh

sudo journalctl \
	-o json \
	-t vpn-server-api-client-connect \
	-t vpn-server-api-client-disconnect \
	| vpn-server-api-parse-journal
