<?php

declare( strict_types=1 );

namespace Blockify\Theme;

use function defined;
use function is_a;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function mb_convert_encoding;
use DOMDocument;
use DOMElement;

/**
 * Returns a formatted DOMDocument object from a given string.
 *
 * @since 0.0.2
 *
 * @param string $html HTML string to convert to DOM.
 *
 * @return \DOMDocument
 */
function dom( string $html ): DOMDocument {
	$dom = new DOMDocument();

	if ( ! $html ) {
		return $dom;
	}

	$libxml_previous_state = libxml_use_internal_errors( true );

	$dom->preserveWhiteSpace = true;

	if ( defined( 'LIBXML_HTML_NOIMPLIED' ) && defined( 'LIBXML_HTML_NODEFDTD' ) ) {
		$options = LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD;
	} elseif ( defined( 'LIBXML_HTML_NOIMPLIED' ) ) {
		$options = LIBXML_HTML_NOIMPLIED;
	} elseif ( defined( 'LIBXML_HTML_NODEFDTD' ) ) {
		$options = LIBXML_HTML_NODEFDTD;
	} else {
		$options = 0;
	}

	$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ), $options );

	$dom->formatOutput = true;

	libxml_clear_errors();
	libxml_use_internal_errors( $libxml_previous_state );

	return $dom;
}

/**
 * Returns a formatted DOMElement object from a DOMDocument object.
 *
 * @since 0.4.0
 *
 * @param string $tag            HTML tag.
 * @param mixed  $dom_or_element DOMDocument or DOMElement.
 * @param int    $index          Index of element to return.
 *
 * @return \DOMElement|null
 */
function get_dom_element( string $tag, $dom_or_element, int $index = 0 ) {
	if ( ! is_a( $dom_or_element, DOMDocument::class ) && ! is_a( $dom_or_element, DOMElement::class ) ) {
		return null;
	}

	$element = $dom_or_element->getElementsByTagName( $tag )->item( $index );

	if ( ! $element ) {
		return null;
	}

	return dom_element( $element );
}

/**
 * Casts a DOMNode to a DOMElement.
 *
 * @since 0.4.0
 *
 * @param mixed $node DOMNode to cast to DOMElement.
 *
 * @return \DOMElement|null
 */
function dom_element( $node ) {
	if ( $node->nodeType === XML_ELEMENT_NODE ) {
		/* @var \DOMElement $node DOM Element node */
		return $node;
	}

	return null;
}

/**
 * Returns an HTML element with a replaced tag.
 *
 * @since 0.0.20
 *
 * @param DOMElement $element DOM Element to change.
 * @param string     $name    Tag name, e.g: 'div'.
 *
 * @return DOMElement
 */
function change_tag_name( DOMElement $element, string $name ): DOMElement {
	if ( ! $element->ownerDocument ) {
		return new DOMElement( $name );
	}

	$child_nodes = [];

	foreach ( $element->childNodes as $child ) {
		$child_nodes[] = $child;
	}

	$new_element = $element->ownerDocument->createElement( $name );

	foreach ( $child_nodes as $child ) {
		$child2 = $element->ownerDocument->importNode( $child, true );
		$new_element->appendChild( $child2 );
	}

	foreach ( $element->attributes as $attr_node ) {
		$attr_name  = $attr_node->nodeName;
		$attr_value = $attr_node->nodeValue;

		$new_element->setAttribute( $attr_name, $attr_value );
	}

	if ( $element->parentNode ) {
		$element->parentNode->replaceChild( $new_element, $element );
	}

	return $new_element;
}

