<?php

namespace mindplay\timber;

/**
 * This interface is deliberately empty.
 *
 * It serves as a tag, which indicates compatibility with {@see Dispatcher} - which
 * means, your controller implements a single public method named run(), which may
 * take any number of arguments. These arguments will be filled with parameters
 * defined in route patterns - for example, a signature like run($id) will be
 * provided with a parameter from a pattern such as '/user/<id:int>', etc.
 *
 * The use of Dispatcher, and this interface, is optional - you can choose to
 * implement your own dispatcher, based on your preferred conventions.
 */
interface Controller
{
}
