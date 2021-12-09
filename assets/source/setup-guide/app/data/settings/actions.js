/**
 * External dependencies
 */
import { select, dispatch } from '@wordpress/data';
import { apiFetch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import TYPES from './action-types';
import { STORE_NAME, WC_ADMIN_NAMESPACE, OPTIONS_NAME } from './constants';
import { REPORTS_STORE_NAME } from '../../../../catalog-sync/data';

export function receiveSettings( settings ) {
	return {
		type: TYPES.RECEIVE_SETTINGS,
		settings,
	};
}

export function setRequestingError( error, name ) {
	return {
		type: TYPES.SET_REQUESTING_ERROR,
		error,
		name,
	};
}

export function setUpdatingError( error ) {
	return {
		type: TYPES.SET_UPDATING_ERROR,
		error,
	};
}

export function setIsUpdating( isUpdating ) {
	return {
		type: TYPES.SET_IS_UPDATING,
		isUpdating,
	};
}

/**
 * Saves the data to be updated in the store
 *
 * @param {Object} data The data to be updated
 * @param {boolean} reset True if we want to empty the updatedData prop (normally after DB store)
 * @return {Object} The reducer definition
 */
export function setUpdatedData( data, reset = false ) {
	return {
		type: TYPES.SET_UPDATED_DATA,
		data,
		reset,
	};
}

/**
 * Update the settings in the store or in DB
 *
 * @param {Object} data The data to be updated
 * @param {boolean} saveToDb If true it persists the changes in DB and empties the updateData prop in the store
 * @return {Generator<{success}, void, ?>} A generator for the data update
 */
export function* updateSettings( data, saveToDb = false ) {
	yield setUpdatedData( data, saveToDb );
	yield receiveSettings( data );

	if ( ! saveToDb ) {
		return { success: true };
	}

	yield setIsUpdating( true );
	const settings = yield select( STORE_NAME ).getSettings();

	try {
		const results = yield apiFetch( {
			path: WC_ADMIN_NAMESPACE + '/options',
			method: 'POST',
			data: {
				[ OPTIONS_NAME ]: settings,
			},
		} );

		dispatch( REPORTS_STORE_NAME ).resetFeed();
		dispatch( REPORTS_STORE_NAME ).invalidateResolutionForStore();

		yield setIsUpdating( false );
		return { success: results[ OPTIONS_NAME ] };
	} catch ( error ) {
		yield setUpdatingError( error );
		throw error;
	}
}
