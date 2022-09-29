/**
 * External dependencies
 */
import '@wordpress/notices';
import { useSelect } from '@wordpress/data';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { recordEvent } from '@woocommerce/tracks';

/**
 * Internal dependencies
 */
import SyncState from './sections/SyncState';
import AdCreditsNotice from './sections/AdCreditsNotice';
import SyncIssues from './sections/SyncIssues';
import TransientNotices from './components/TransientNotices';
import HealthCheck from '../setup-guide/app/components/HealthCheck';
import { useCreateNotice, useDismissAdsModalDispatch } from './helpers/effects';
import NavigationClassic from '../components/navigation-classic';
import OnboardingModal from './components/OnboardingModal';
import OnboardingSuccessModal from './components/OnboardingSuccessModal';
import { USER_INTERACTION_STORE_NAME } from './data';
import { useSettingsSelect } from '../setup-guide/app/helpers/effects';

/**
 * Opening a modal.
 *
 * @event wcadmin_pfw_modal_open
 * @property {string} name Ads Onboarding Modal.
 * @property {string} context catalog-sync
 */
/**
 * Closing a modal.
 *
 * @event wcadmin_pfw_modal_closed
 * @property {string} name Ads Onboarding Modal.
 * @property {string} context catalog-sync
 */

/**
 * Catalog Sync Tab.
 *
 * @fires wcadmin_pfw_modal_open with `{ name: 'ads-credits-onboarding' }`
 * @fires wcadmin_pfw_modal_close with `{ name: 'ads-credits-onboarding' }`
 *
 * @return {JSX.Element} rendered component
 */
const CatalogSyncApp = () => {
	const adsCampaignIsActive = useSettingsSelect()?.ads_campaign_is_active;

	useCreateNotice( wcSettings.pinterest_for_woocommerce.error );
	const [ isOnboardingModalOpen, setIsOnboardingModalOpen ] = useState(
		false
	);
	const [ isAdCreditsNoticeOpen, setIsAdCreditsNoticeOpen ] = useState(
		false
	);

	const userInteractions = useSelect( ( select ) =>
		select( USER_INTERACTION_STORE_NAME ).getUserInteractions()
	);

	const userInteractionsLoaded = useSelect( ( select ) =>
		select( USER_INTERACTION_STORE_NAME ).areInteractionsLoaded()
	);

	const openOnboardingModal = useCallback( () => {
		if (
			userInteractionsLoaded === false ||
			userInteractions?.ads_modal_dismissed
		) {
			return;
		}

		setIsOnboardingModalOpen( true );
		recordEvent( 'pfw_modal_open', {
			context: 'catalog-sync',
			name: 'ads-credits-onboarding',
		} );
	}, [ userInteractions?.ads_modal_dismissed, userInteractionsLoaded ] );

	const openAdsCreditsNotice = useCallback( () => {
		if (
			userInteractionsLoaded === false ||
			userInteractions?.ads_notice_dismissed
		) {
			return;
		}

		setIsAdCreditsNoticeOpen( true );
	}, [ userInteractions?.ads_notice_dismissed, userInteractionsLoaded ] );

	const closeOnboardingModal = () => {
		setIsOnboardingModalOpen( false );
		handleSetDismissAdsModal();
		openAdsCreditsNotice();
		recordEvent( 'pfw_modal_closed', {
			context: 'catalog-sync',
			name: 'ads-credits-onboarding',
		} );
	};

	const setDismissAdsModal = useDismissAdsModalDispatch();
	const handleSetDismissAdsModal = useCallback( async () => {
		try {
			await setDismissAdsModal();
		} catch ( error ) {}
	}, [ setDismissAdsModal ] );

	useEffect( () => {
		openOnboardingModal();
	}, [ openOnboardingModal ] );

	return (
		<div className="pinterest-for-woocommerce-catalog-sync">
			<HealthCheck />
			<NavigationClassic />

			<TransientNotices />
			<div className="pinterest-for-woocommerce-catalog-sync__container">
				<SyncState />
				{ isAdCreditsNoticeOpen && <AdCreditsNotice /> }
				<SyncIssues />
			</div>
			{ isOnboardingModalOpen &&
				( adsCampaignIsActive ? (
					<OnboardingModal onCloseModal={ closeOnboardingModal } />
				) : (
					<OnboardingSuccessModal
						onCloseModal={ closeOnboardingModal }
					/>
				) ) }
		</div>
	);
};

export default CatalogSyncApp;
