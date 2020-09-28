/**
 * JavaScript printf/sprintf functions.
 * http://hexmen.com/blog/2007/03/printf-sprintf/
 *
 * This code is unrestricted: you are free to use it however you like.
 * 
 * The functions should work as expected, performing left or right alignment,
 * truncating strings, outputting numbers with a required precision etc.
 *
 * For complex cases these functions follow the Perl implementations of
 * (s)printf, allowing arguments to be passed out-of-order, and to set
 * precision and output-length from other argument
 *
 * See http://perldoc.perl.org/functions/sprintf.html for more information.
 *
 * Implemented flags:
 *
 * - zero or space-padding (default: space)
 *     sprintf("%4d", 3) ->  "   3"
 *     sprintf("%04d", 3) -> "0003"
 *
 * - left and right-alignment (default: right)
 *     sprintf("%3s", "a") ->  "  a"
 *     sprintf("%-3s", "b") -> "b  "
 *
 * - out of order arguments (good for templates & message formats)
 *     sprintf("Estimate: %2$d units total: %1$.2f total", total, quantity)
 *
 * - binary, octal and hex prefixes (default: none)
 *     sprintf("%b", 13) ->    "1101"
 *     sprintf("%#b", 13) ->   "0b1101"
 *     sprintf("%#06x", 13) -> "0x000d"
 *
 * - positive number prefix (default: none)
 *     sprintf("%d", 3) -> "3"
 *     sprintf("%+d", 3) -> "+3"
 *     sprintf("% d", 3) -> " 3"
 *
 * - min/max width (with truncation); e.g. "%9.3s" and "%-9.3s"
 *     sprintf("%5s", "catfish") ->    "catfish"
 *     sprintf("%.5s", "catfish") ->   "catfi"
 *     sprintf("%5.3s", "catfish") ->  "  cat"
 *     sprintf("%-5.3s", "catfish") -> "cat  "
 *
 * - precision (see note below); e.g. "%.2f"
 *     sprintf("%.3f", 2.1) ->     "2.100"
 *     sprintf("%.3e", 2.1) ->     "2.100e+0"
 *     sprintf("%.3g", 2.1) ->     "2.10"
 *     sprintf("%.3p", 2.1) ->     "2.1"
 *     sprintf("%.3p", '2.100') -> "2.10"
 *
 * Deviations from perl spec:
 * - %n suppresses an argument
 * - %p and %P act like %g, but without over-claiming accuracy:
 *   Compare:
 *     sprintf("%.3g", "2.1") -> "2.10"
 *     sprintf("%.3p", "2.1") -> "2.1"
 *
 * @version 2011.09.23
 * @author Ash Searle
 */
function sprintf() {
    function pad(str, len, chr, leftJustify) {
	var padding = (str.length >= len) ? '' : Array(1 + len - str.length >>> 0).join(chr);
	return leftJustify ? str + padding : padding + str;

    }

    function justify(value, prefix, leftJustify, minWidth, zeroPad) {
	var diff = minWidth - value.length;
	if (diff > 0) {
	    if (leftJustify || !zeroPad) {
		value = pad(value, minWidth, ' ', leftJustify);
	    } else {
		value = value.slice(0, prefix.length) + pad('', diff, '0', true) + value.slice(prefix.length);
	    }
	}
	return value;
    }

    var a = arguments, i = 0, format = a[i++];
    return format.replace(sprintf.regex, function(substring, valueIndex, flags, minWidth, _, precision, type) {
	    if (substring == '%%') return '%';

	    // parse flags
	    var leftJustify = false, positivePrefix = '', zeroPad = false, prefixBaseX = false;
	    for (var j = 0; flags && j < flags.length; j++) switch (flags.charAt(j)) {
		case ' ': positivePrefix = ' '; break;
		case '+': positivePrefix = '+'; break;
		case '-': leftJustify = true; break;
		case '0': zeroPad = true; break;
		case '#': prefixBaseX = true; break;
	    }

	    // parameters may be null, undefined, empty-string or real valued
	    // we want to ignore null, undefined and empty-string values

	    if (!minWidth) {
		minWidth = 0;
	    } else if (minWidth == '*') {
		minWidth = +a[i++];
	    } else if (minWidth.charAt(0) == '*') {
		minWidth = +a[minWidth.slice(1, -1)];
	    } else {
		minWidth = +minWidth;
	    }

	    // Note: undocumented perl feature:
	    if (minWidth < 0) {
		minWidth = -minWidth;
		leftJustify = true;
	    }

	    if (!isFinite(minWidth)) {
		throw new Error('sprintf: (minimum-)width must be finite');
	    }

	    if (precision && precision.charAt(0) == '*') {
		precision = +a[(precision == '*') ? i++ : precision.slice(1, -1)];
		if (precision < 0) {
		    precision = null;
		}
	    }

	    if (precision == null) {
		precision = 'fFeE'.indexOf(type) > -1 ? 6 : (type == 'd') ? 0 : void(0);
	    } else {
		precision = +precision;
	    }

	    // grab value using valueIndex if required?
	    var value = valueIndex ? a[valueIndex.slice(0, -1)] : a[i++];
	    var prefix, base;

	    switch (type) {
		case 'c': value = String.fromCharCode(+value);
		case 's': {
			      // If you'd rather treat nulls as empty-strings, uncomment next line:
			      // if (value == null) return '';

			      value = String(value);
			      if (precision != null) {
				  value = value.slice(0, precision);
			      }
			      prefix = '';
			      break;
			  }
		case 'b': base = 2; break;
		case 'o': base = 8; break;
		case 'u': base = 10; break;
		case 'x': case 'X': base = 16; break;
		case 'i':
		case 'd': {
			      var number = parseInt(+value);
			      if (isNaN(number)) {
				  return '';
			      }
			      prefix = number < 0 ? '-' : positivePrefix;
			      value = prefix + pad(String(Math.abs(number)), precision, '0', false);
			      break;
			  }
		case 'e': case 'E':
		case 'f': case 'F':
		case 'g': case 'G':
		case 'p': case 'P':
		          {
			      var number = +value;
			      if (isNaN(number)) {
				  return '';
			      }
			      prefix = number < 0 ? '-' : positivePrefix;
			      var method;
			      if ('p' != type.toLowerCase()) {
				  method = ['toExponential', 'toFixed', 'toPrecision']['efg'.indexOf(type.toLowerCase())];
			      } else {
				  // Count significant-figures, taking special-care of zeroes ('0' vs '0.00' etc.)
				  var sf = String(value).replace(/[eE].*|[^\d]/g, '');
				  sf = (number ? sf.replace(/^0+/,'') : sf).length;
				  precision = precision ? Math.min(precision, sf) : precision;
				  method = (!precision || precision <= sf) ? 'toPrecision' : 'toExponential';
			      }
			      var number_str = Math.abs(number)[method](precision);
			      // number_str = thousandSeparation ? thousand_separate(number_str): number_str;
			      value = prefix + number_str;
			      break;
			  }
		case 'n': return '';
		default: return substring;
	    }

	    if (base) {
		// cast to non-negative integer:
		var number = value >>> 0;
		prefix = prefixBaseX && base != 10 && number && ['0b', '0', '0x'][base >> 3] || '';
		value = prefix + pad(number.toString(base), precision || 0, '0', false);
	    }
	    var justified = justify(value, prefix, leftJustify, minWidth, zeroPad);
	    return ('EFGPX'.indexOf(type) > -1) ? justified.toUpperCase() : justified;
    });
}
sprintf.regex = /%%|%(\d+\$)?([-+#0 ]*)(\*\d+\$|\*|\d+)?(\.(\*\d+\$|\*|\d+))?([scboxXuidfegpEGP])/g;

/**
 * Trival printf implementation, probably only useful during page-load.
 * Note: you may as well use "document.write(sprintf(....))" directly
 */
function printf() {
    // delegate the work to sprintf in an IE5 friendly manner:
    var i = 0, a = arguments, args = Array(arguments.length);
    while (i < args.length) args[i] = 'a[' + (i++) + ']';
    document.write(eval('sprintf(' + args + ')'));
}
