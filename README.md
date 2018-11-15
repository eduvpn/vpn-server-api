# Introduction

This project is the server API of the Let's Connect! software. It is used by 
both [vpn-admin-portal](https://github.com/eduvpn/vpn-admin-portal) and 
[vpn-user-portal](https://github.com/eduvpn/vpn-user-portal). It contains the
CA and a database containing information about the users and links issued 
certificates to users.

# Third Party Software

Because CentOS 7 EPEL does not have easy-rsa 3, a copy is included in the tree 
in the `easy-rsa` directory. It is licensed under the GPL version 2.0.

Upstream: [https://github.com/OpenVPN/easy-rsa](https://github.com/OpenVPN/easy-rsa).
