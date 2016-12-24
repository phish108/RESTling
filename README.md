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

The easiest way of using RESTling is through composer. Add it as a requirement
to your project and you are ready to go.

In order to take full benefit of the REST concepts, RESTling needs to get
installed on a web-server that passes all HTTP request methods to scripts.

Apache versions prior to 2.4 refused to hand down PUT and DELETE requests to
different scripts in the same directory. Therefore, the PUT examples won't
work with the old Apache Version.

Installation
------------

RESTling supports now composer. To install the library use

```
 $ cd /Path/To/Restling
 $ composer install
```

License
-------

RESTling is licensed under the GNU Affero License.

Contributors
------------

* Christian Glahn
* Evangelia Mitsopoulou
