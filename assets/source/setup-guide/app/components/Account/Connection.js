/**
 * External dependencies
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Spinner } from '@woocommerce/components';
import { getNewPath } from '@woocommerce/navigation';
import { recordEvent } from '@woocommerce/tracks';
import {
	Button,
	CardBody,
	Flex,
	FlexItem,
	FlexBlock,
	Modal,
	__experimentalText as Text, // eslint-disable-line @wordpress/no-unsafe-wp-apis --- _experimentalText unlikely to change/disappear and also used by WC Core
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import { useCreateNotice } from '../../helpers/effects';

const PinterestLogo = () => {
	return (
		<img
			src={
				wcSettings.pinterest_for_woocommerce.pluginUrl +
				'/assets/images/pinterest-logo.svg'
			}
			alt=""
		/>
	);
};

/**
 * Clicking on "Connect" Pinterest account button.
 *
 * @event wcadmin_pfw_account_connect_button_click
 */
/**
 * Clicking on "Disconnect" Pinterest account button during account setup.
 *
 * @event wcadmin_pfw_account_disconnect_button_click
 */

/**
 * Pinterest account connection component.
 *
 * @fires wcadmin_pfw_account_connect_button_click
 * @fires wcadmin_pfw_account_disconnect_button_click
 *
 * @param {Object} props React props.
 * @param {boolean} props.isConnected
 * @param {Function} props.setIsConnected
 * @param {Object} props.accountData
 * @return {JSX.Element} Rendered element.
 */
const AccountConnection = ( { isConnected, setIsConnected, accountData } ) => {
	const createNotice = useCreateNotice();

	const [ isConfirmationModalOpen, setIsConfirmationModalOpen ] = useState(
		false
	);

	const openConfirmationModal = () => {
		setIsConfirmationModalOpen( true );
		recordEvent( 'wcadmin_pfw_account_disconnect_button_click' );
	};

	const closeConfirmationModal = () => {
		setIsConfirmationModalOpen( false );
	};

	const renderConfirmationModal = () => {
		return (
			<Modal
				title={
					<>{ __( 'Are you sure?', 'pinterest-for-woocommerce' ) }</>
				}
				onRequestClose={ closeConfirmationModal }
				className="woocommerce-setup-guide__step-modal"
			>
				<div className="woocommerce-setup-guide__step-modal__wrapper">
					<p>
						{ __(
							'Are you sure you want to disconnect this account?',
							'pinterest-for-woocommerce'
						) }
					</p>
					<div className="woocommerce-setup-guide__step-modal__buttons">
						<Button
							isDestructive
							isSecondary
							onClick={ handleDisconnectAccount }
						>
							{ __(
								"Yes, I'm sure",
								'pinterest-for-woocommerce'
							) }
						</Button>
						<Button isTertiary onClick={ closeConfirmationModal }>
							{ __( 'Cancel', 'pinterest-for-woocommerce' ) }
						</Button>
					</div>
				</div>
			</Modal>
		);
	};

	const handleDisconnectAccount = async () => {
		closeConfirmationModal();

		try {
			await apiFetch( {
				path:
					wcSettings.pinterest_for_woocommerce.apiRoute +
					'/auth_disconnect',
				method: 'POST',
			} );

			setIsConnected( false );

			// Force reload WC admin page to initiate the relevant dependencies of the Dashboard page.
			const path = getNewPath( {}, '/pinterest/landing', {} );

			window.location = new URL( wcSettings.adminUrl + path );
		} catch ( error ) {
			createNotice(
				'error',
				error.message ||
					__(
						'There was a problem while trying to disconnect.',
						'pinterest-for-woocommerce'
					)
			);
		}
	};

	return (
		<CardBody size="large">
			{ isConnected === true ? ( // eslint-disable-line no-nested-ternary --- Code is reasonable readable
				<Flex direction="row" className="connection-info">
					{ accountData?.id ? (
						<>
							<FlexItem className="logo">
								<PinterestLogo />
							</FlexItem>

							<FlexBlock className="account-label">
								<Text variant="body">
									{ accountData.username }

									<span className="account-type">
										{ ' - (' }
										{ accountData.is_partner
											? __(
													'Business account',
													'pinterest-for-woocommerce'
											  )
											: __(
													'Personal account',
													'pinterest-for-woocommerce'
											  ) }
										{ ')' }
									</span>
								</Text>
							</FlexBlock>

							<FlexItem>
								<Button
									isLink
									isDestructive
									onClick={ openConfirmationModal }
								>
									{ __(
										'Disconnect',
										'pinterest-for-woocommerce'
									) }
								</Button>
							</FlexItem>
						</>
					) : (
						<Spinner />
					) }
				</Flex>
			) : isConnected === false ? (
				<Flex direction="row" className="connection-info">
					<FlexItem className="logo">
						<PinterestLogo />
					</FlexItem>

					<FlexBlock>
						<Text variant="subtitle">
							{ __(
								'Connect your Pinterest Account',
								'pinterest-for-woocommerce'
							) }
						</Text>
					</FlexBlock>

					<FlexItem>
						<Button
							isSecondary
							href={
								wcSettings.pinterest_for_woocommerce
									.serviceLoginUrl
							}
							onClick={ () =>
								recordEvent(
									'wcadmin_pfw_account_connect_button_click'
								)
							}
						>
							{ __( 'Connect', 'pinterest-for-woocommerce' ) }
						</Button>
					</FlexItem>
				</Flex>
			) : (
				<Spinner />
			) }

			{ isConfirmationModalOpen && renderConfirmationModal() }
		</CardBody>
	);
};

export default AccountConnection;
