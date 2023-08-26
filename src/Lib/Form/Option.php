<?php
namespace Sannomiya\Form;

abstract class Option
{
    const Insert = 1;
    const Copy = 2;
    const Search = 4;
    const Import = 8;
    const Export = 16;
    const Update = 32;
    const Delete = 64;
    const Excel = 128;
    const Order =256;
}
