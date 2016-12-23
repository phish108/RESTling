RESTling
========

A lightweight REST service class for rapid prototyping of REST Services.

SYNOPSIS
--------

RESTling is a lightweight class framework for generic REST-Services written
in PHP. RESTling offers simple debugging features and a session validation
layer for OAuth and similar authorization concepts.

Using RESTling
--------------

You can download and install RESTling directly into your 'include' directory
of your project - or you can use git's submodule function in order to keep up
to date with the code.

In order to take full benefit of the REST concepts, RESTling needs to get
installed on a web-server that passes all HTTP request methods to scripts.

Apache 2.2 refuses to hand down PUT and DELETE requests to different scripts
in the same directory. Therefore, the PUT examples won't work with the old
Apache Version.

License
-------

RESTling is licensed under the GNU Affero License.

Contributors
------------

* Christian Glahn
* Evangelia Mitsopoulou
