<?php

// autoload_classmap.php @generated by Composer

$vendorDir = dirname(__DIR__);
$baseDir = dirname($vendorDir);

return array(
    'Appsero\\Client' => $vendorDir . '/appsero/client/src/Client.php',
    'Appsero\\Insights' => $vendorDir . '/appsero/client/src/Insights.php',
    'Appsero\\License' => $vendorDir . '/appsero/client/src/License.php',
    'Appsero\\Updater' => $vendorDir . '/appsero/client/src/Updater.php',
    'Composer\\InstalledVersions' => $vendorDir . '/composer/InstalledVersions.php',
    'ContactImportAction' => $baseDir . '/app/API/Actions/Admin/Contact/ContactImportAction.php',
    'ContactProfileAction' => $baseDir . '/app/API/Actions/Admin/Contact/ContactProfileAction.php',
    'Flatted' => $baseDir . '/app/Internal/FormBuilder/node_modules/flatted/php/flatted.php',
    'FlattedString' => $baseDir . '/app/Internal/FormBuilder/node_modules/flatted/php/flatted.php',
    'GeneralFieldActions' => $baseDir . '/app/API/Actions/Admin/GeneralFieldActions.php',
    'MRM\\Common\\MrmCommon' => $baseDir . '/app/MrmCommon.php',
    'MailMint' => $baseDir . '/includes/MailMint.php',
    'MailMint\\App\\Actions\\Hooks' => $baseDir . '/app/Internal/Actions/Hooks.php',
    'MailMint\\App\\Helper' => $baseDir . '/app/Helper.php',
    'MailMint\\App\\Internal\\FormBuilder\\Storage' => $baseDir . '/app/Internal/FormBuilder/templates/Storage.php',
    'MintMail\\App\\Internal\\Automation' => $baseDir . '/app/Internal/Automation/Automation.php',
    'MintMail\\App\\Internal\\Automation\\ActionScheduler' => $baseDir . '/app/Internal/Automation/Scheduler/ActionScheduler.php',
    'MintMail\\App\\Internal\\Automation\\Action\\AbstractAutomationAction' => $baseDir . '/app/Internal/Automation/Actions/AbstractAutomationAction.php',
    'MintMail\\App\\Internal\\Automation\\Action\\AddList' => $baseDir . '/app/Internal/Automation/Actions/AddList.php',
    'MintMail\\App\\Internal\\Automation\\Action\\AddTag' => $baseDir . '/app/Internal/Automation/Actions/AddTag.php',
    'MintMail\\App\\Internal\\Automation\\Action\\AutomationAction' => $baseDir . '/app/Internal/Automation/Actions/AutomationActions.php',
    'MintMail\\App\\Internal\\Automation\\Action\\Delay' => $baseDir . '/app/Internal/Automation/Actions/Delay.php',
    'MintMail\\App\\Internal\\Automation\\Action\\SendMail' => $baseDir . '/app/Internal/Automation/Actions/SendMail.php',
    'MintMail\\App\\Internal\\Automation\\AutomationJobModel' => $baseDir . '/app/Internal/Automation/Core/DataStore/AutomationJobStore.php',
    'MintMail\\App\\Internal\\Automation\\AutomationLogModel' => $baseDir . '/app/Internal/Automation/Core/DataStore/AutomationLogStore.php',
    'MintMail\\App\\Internal\\Automation\\AutomationManager' => $baseDir . '/app/Internal/Automation/AutomationManager.php',
    'MintMail\\App\\Internal\\Automation\\AutomationModel' => $baseDir . '/app/Internal/Automation/Core/DataStore/AutomationStore.php',
    'MintMail\\App\\Internal\\Automation\\AutomationStepModel' => $baseDir . '/app/Internal/Automation/Core/DataStore/AutomationStepStore.php',
    'MintMail\\App\\Internal\\Automation\\Automation_Connector' => $baseDir . '/app/Internal/Automation/Connectors/AbstractAutomationConnector.php',
    'MintMail\\App\\Internal\\Automation\\Connector' => $baseDir . '/app/Internal/Automation/Connectors/AutomationConnector.php',
    'MintMail\\App\\Internal\\Automation\\Connector\\ConnectorMintForm' => $baseDir . '/app/Internal/Automation/Connectors/MintForm/ConnectorMintForm.php',
    'MintMail\\App\\Internal\\Automation\\Connector\\ConnectorWordPress' => $baseDir . '/app/Internal/Automation/Connectors/WP/ConnectorWordpress.php',
    'MintMail\\App\\Internal\\Automation\\Connector\\trigger\\MintFormTriggers' => $baseDir . '/app/Internal/Automation/Connectors/MintForm/Triggers/MintFormTriggers.php',
    'MintMail\\App\\Internal\\Automation\\Connector\\trigger\\PostPublishedTriggers' => $baseDir . '/app/Internal/Automation/Connectors/WP/Triggers/PostPublishedTriggers.php',
    'MintMail\\App\\Internal\\Automation\\Connector\\trigger\\WordpressTriggers' => $baseDir . '/app/Internal/Automation/Connectors/WP/Triggers/WordPressTriggers.php',
    'MintMail\\App\\Internal\\Automation\\HelperFunctions' => $baseDir . '/app/Internal/Automation/Core/Helper/HelperFunctions.php',
    'MintMail\\App\\Internal\\Automation\\Hooks\\AutomationHooks' => $baseDir . '/app/Internal/Automation/Core/Hooks/AutomationHook.php',
    'MintMail\\App\\Internal\\Automation\\Recipe\\AutomationRecipe' => $baseDir . '/app/Internal/Automation/Recipe/AutomationRecipe.php',
    'Mint\\App\\Classes\\Mailer' => $baseDir . '/app/DataStores/Mailer.php',
    'Mint\\App\\Classes\\Message' => $baseDir . '/app/DataStores/Message.php',
    'Mint\\App\\Classes\\WPRemoteRequestHandler' => $baseDir . '/app/DataStores/WPRemoteRequestHandler.php',
    'Mint\\App\\Database\\Repositories\\Email\\Template' => $baseDir . '/app/Database/Repositories/Email/Template.php',
    'Mint\\App\\Internal\\Actions\\Handlers\\RedirectionHandler' => $baseDir . '/app/Internal/Actions/Handlers/RedirectionHandler.php',
    'Mint\\App\\Internal\\Cron\\BackgroundProcessHelper' => $baseDir . '/app/Internal/Cron/BackgroundProcessHelper.php',
    'Mint\\App\\Internal\\EmailCustomization\\Render\\BlockRender' => $baseDir . '/app/Internal/EmailCustomization/Render/BlockRender.php',
    'Mint\\App\\Internal\\EmailCustomization\\Render\\EmailRender' => $baseDir . '/app/Internal/EmailCustomization/Render/EmailRender.php',
    'Mint\\App\\Internal\\EmailCustomization\\Shortcode\\WCShortcode' => $baseDir . '/app/Internal/EmailCustomization/Shortcode/WCShortcode.php',
    'Mint\\App\\Internal\\EmailCustomization\\WooCommerce\\EmailTrigger' => $baseDir . '/app/Internal/EmailCustomization/WooCommerce/EmailTrigger.php',
    'Mint\\App\\Internal\\FormBuilder\\MintFormBlock' => $baseDir . '/app/Internal/FormBuilder/FormBlock/MintFormBlock.php',
    'Mint\\App\\Internal\\Mailers\\BaseMailer' => $baseDir . '/app/Internal/Mailers/BaseMailer.php',
    'Mint\\App\\Internal\\Mailers\\WpmailMailer' => $baseDir . '/app/Internal/Mailers/WPMailMailer.php',
    'Mint\\MRM\\API\\Actions\\Action' => $baseDir . '/app/API/Actions/Action.php',
    'Mint\\MRM\\API\\Actions\\ActionCreator' => $baseDir . '/app/API/Actions/ActionCreator.php',
    'Mint\\MRM\\API\\Actions\\AnalyticsActionCreator' => $baseDir . '/app/API/Actions/Creators/Admin/AnalyticsActionCreator.php',
    'Mint\\MRM\\API\\Actions\\ComplianceAction' => $baseDir . '/app/API/Actions/Admin/ComplianceAction.php',
    'Mint\\MRM\\API\\Actions\\ContactImportActionCreator' => $baseDir . '/app/API/Actions/Creators/Admin/Contact/ContactImportActionCreator.php',
    'Mint\\MRM\\API\\Actions\\ContactProfileActionCreator' => $baseDir . '/app/API/Actions/Creators/Admin/Contact/ContactProfileActionCreator.php',
    'Mint\\MRM\\API\\Actions\\CookieAction' => $baseDir . '/app/API/Actions/Frontend/CookieAction.php',
    'Mint\\MRM\\API\\Actions\\CookieActionCreator' => $baseDir . '/app/API/Actions/Creators/Frontend/CookieActionCreator.php',
    'Mint\\MRM\\API\\Actions\\FormAction' => $baseDir . '/app/API/Actions/Frontend/FormAction.php',
    'Mint\\MRM\\API\\Actions\\FormActionCreator' => $baseDir . '/app/API/Actions/Creators/Frontend/FormActionCreator.php',
    'Mint\\MRM\\API\\Actions\\GeneralFieldActionCreator' => $baseDir . '/app/API/Actions/Creators/Admin/GeneralFieldActionCreator.php',
    'Mint\\MRM\\API\\Actions\\ListActionCreator' => $baseDir . '/app/API/Actions/Creators/Admin/ListActionCreator.php',
    'Mint\\MRM\\API\\Actions\\ListActions' => $baseDir . '/app/API/Actions/Admin/ListActions.php',
    'Mint\\MRM\\API\\Actions\\PreferenceAction' => $baseDir . '/app/API/Actions/Frontend/PreferenceAction.php',
    'Mint\\MRM\\API\\Actions\\PreferenceActionCreator' => $baseDir . '/app/API/Actions/Creators/Frontend/PreferenceActionCreator.php',
    'Mint\\MRM\\API\\Actions\\TagActionCreator' => $baseDir . '/app/API/Actions/Creators/Admin/TagActionCreator.php',
    'Mint\\MRM\\API\\Actions\\TagActions' => $baseDir . '/app/API/Actions/Admin/TagActions.php',
    'Mint\\MRM\\API\\Actions\\TemplateActionCreator' => $baseDir . '/app/API/Actions/Creators/Admin/Email/TemplateActionCreator.php',
    'Mint\\MRM\\API\\Controllers\\BaseController' => $baseDir . '/app/API/Controllers/BaseController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\AdminBaseController' => $baseDir . '/app/API/Controllers/Admin/AdminBaseController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\AutomationController' => $baseDir . '/app/Internal/Automation/Core/API/Controllers/AutomationController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\AutomationJobController' => $baseDir . '/app/Internal/Automation/Core/API/Controllers/AutomationJobController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\AutomationLogController' => $baseDir . '/app/Internal/Automation/Core/API/Controllers/AutomationLogController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\AutomationStepController' => $baseDir . '/app/Internal/Automation/Core/API/Controllers/AutomationStepController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\BusinessBasicSettingController' => $baseDir . '/app/API/Controllers/Admin/BusinessBasicSettingController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\BusinessSocialSettingController' => $baseDir . '/app/API/Controllers/Admin/BusinessSocialSettingController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\CampaignController' => $baseDir . '/app/API/Controllers/Admin/CampaignController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\CampaignEmailController' => $baseDir . '/app/API/Controllers/Admin/CampaignEmailController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\ComplianceSettingController' => $baseDir . '/app/API/Controllers/Admin/ComplianceSettingController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\ContactController' => $baseDir . '/app/API/Controllers/Admin/ContactController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\ContactImportController' => $baseDir . '/app/API/Controllers/Admin/Contact/ContactImportController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\ContactPivotController' => $baseDir . '/app/API/Controllers/Admin/ContactPivotController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\ContactProfileController' => $baseDir . '/app/API/Controllers/Admin/Contact/ContactProfileController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\DashboardController' => $baseDir . '/app/API/Controllers/Admin/DashboardController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\EmailBuilderController' => $baseDir . '/app/API/Controllers/Admin/EmailBuilderController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\EmailSettingController' => $baseDir . '/app/API/Controllers/Admin/EmailSettingController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\FieldGroupController' => $baseDir . '/app/API/Controllers/Admin/FieldGroupController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\FormController' => $baseDir . '/app/API/Controllers/Admin/FormController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\GeneralController' => $baseDir . '/app/API/Controllers/Admin/GeneralController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\GeneralFieldController' => $baseDir . '/app/API/Controllers/Admin/GeneralFieldController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\GeneralSettingController' => $baseDir . '/app/API/Controllers/Admin/GeneralSettingController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\ListController' => $baseDir . '/app/API/Controllers/Admin/ListController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\MessageController' => $baseDir . '/app/API/Controllers/Admin/MessageController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\OptinSettingController' => $baseDir . '/app/API/Controllers/Admin/OptinSettingController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\ProductController' => $baseDir . '/app/API/Controllers/Admin/ProductContoller.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\SettingBaseController' => $baseDir . '/app/API/Controllers/Admin/SettingBaseController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\TagController' => $baseDir . '/app/API/Controllers/Admin/TagController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\TemplateController' => $baseDir . '/app/API/Controllers/Admin/Email/TemplateController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\WCSettingController' => $baseDir . '/app/API/Controllers/Admin/WCSettingController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\WPController' => $baseDir . '/app/API/Controllers/Admin/WPController.php',
    'Mint\\MRM\\Admin\\API\\Controllers\\reCaptchaSettingController' => $baseDir . '/app/API/Controllers/Admin/reCaptchaSettingController.php',
    'Mint\\MRM\\Admin\\API\\Routes\\AdminRoute' => $baseDir . '/app/API/Routes/Admin/AdminRoute.php',
    'Mint\\MRM\\Admin\\API\\Routes\\AutomationJobRoute' => $baseDir . '/app/Internal/Automation/Core/API/Routes/AutomationJobRoute.php',
    'Mint\\MRM\\Admin\\API\\Routes\\AutomationLogRoute' => $baseDir . '/app/Internal/Automation/Core/API/Routes/AutomationLogRoute.php',
    'Mint\\MRM\\Admin\\API\\Routes\\AutomationRoute' => $baseDir . '/app/Internal/Automation/Core/API/Routes/AutomationRoute.php',
    'Mint\\MRM\\Admin\\API\\Routes\\AutomationStepRoute' => $baseDir . '/app/Internal/Automation/Core/API/Routes/AutomationStepRoute.php',
    'Mint\\MRM\\Admin\\API\\Routes\\CampaignEmailRoute' => $baseDir . '/app/API/Routes/Admin/CampaignEmailRoute.php',
    'Mint\\MRM\\Admin\\API\\Routes\\CampaignRoute' => $baseDir . '/app/API/Routes/Admin/CampaignRoute.php',
    'Mint\\MRM\\Admin\\API\\Routes\\ContactColumnRoute' => $baseDir . '/app/API/Routes/Admin/ContactColumnRoute.php',
    'Mint\\MRM\\Admin\\API\\Routes\\ContactImportRoute' => $baseDir . '/app/API/Routes/Admin/Contact/ContactImportRoute.php',
    'Mint\\MRM\\Admin\\API\\Routes\\ContactProfileRoute' => $baseDir . '/app/API/Routes/Admin/Contact/ContactProfileRoute.php',
    'Mint\\MRM\\Admin\\API\\Routes\\ContactRoute' => $baseDir . '/app/API/Routes/Admin/ContactRoute.php',
    'Mint\\MRM\\Admin\\API\\Routes\\DashboardRoute' => $baseDir . '/app/API/Routes/Admin/DashboardRoute.php',
    'Mint\\MRM\\Admin\\API\\Routes\\EmailBuilderRoute' => $baseDir . '/app/API/Routes/Admin/EmailBuilderRoute.php',
    'Mint\\MRM\\Admin\\API\\Routes\\FieldGroupRoute' => $baseDir . '/app/API/Routes/Admin/FieldGroupRoute.php',
    'Mint\\MRM\\Admin\\API\\Routes\\FormRoute' => $baseDir . '/app/API/Routes/Admin/FormRoute.php',
    'Mint\\MRM\\Admin\\API\\Routes\\GeneralFieldRoute' => $baseDir . '/app/API/Routes/Admin/GeneralFieldRoute.php',
    'Mint\\MRM\\Admin\\API\\Routes\\GeneralRoute' => $baseDir . '/app/API/Routes/Admin/GeneralRoute.php',
    'Mint\\MRM\\Admin\\API\\Routes\\ListRoute' => $baseDir . '/app/API/Routes/Admin/ListRoute.php',
    'Mint\\MRM\\Admin\\API\\Routes\\SendEmailRoute' => $baseDir . '/app/API/Routes/Admin/SendEmailRoute.php',
    'Mint\\MRM\\Admin\\API\\Routes\\SettingRoute' => $baseDir . '/app/API/Routes/Admin/SettingRoute.php',
    'Mint\\MRM\\Admin\\API\\Routes\\TagRoute' => $baseDir . '/app/API/Routes/Admin/TagRoute.php',
    'Mint\\MRM\\Admin\\API\\Routes\\TemplateRoute' => $baseDir . '/app/API/Routes/Admin/Email/TemplateRoute.php',
    'Mint\\MRM\\Admin\\API\\Routes\\WPRoute' => $baseDir . '/app/API/Routes/Admin/WPRoute.php',
    'Mint\\MRM\\Admin\\API\\Server' => $baseDir . '/app/API/Server.php',
    'Mint\\MRM\\App' => $baseDir . '/app/App.php',
    'Mint\\MRM\\Constants' => $baseDir . '/app/Utilities/Constants.php',
    'Mint\\MRM\\DataBase\\Migration\\DatabaseMigrator' => $baseDir . '/app/Database/Migrations/DatabaseMigrator.php',
    'Mint\\MRM\\DataBase\\Model' => $baseDir . '/app/Database/Model.php',
    'Mint\\MRM\\DataBase\\Models\\CampaignEmailBuilderModel' => $baseDir . '/app/Database/models/CampaignEmailBuilderModel.php',
    'Mint\\MRM\\DataBase\\Models\\CampaignModel' => $baseDir . '/app/Database/models/CampaignModel.php',
    'Mint\\MRM\\DataBase\\Models\\ContactGroupModel' => $baseDir . '/app/Database/models/ContactGroupModel.php',
    'Mint\\MRM\\DataBase\\Models\\ContactGroupPivotModel' => $baseDir . '/app/Database/models/ContactGroupPivotModel.php',
    'Mint\\MRM\\DataBase\\Models\\ContactModel' => $baseDir . '/app/Database/models/ContactModel.php',
    'Mint\\MRM\\DataBase\\Models\\CustomFieldModel' => $baseDir . '/app/Database/models/CustomFieldModel.php',
    'Mint\\MRM\\DataBase\\Models\\DashboardModel' => $baseDir . '/app/Database/models/DashboardModel.php',
    'Mint\\MRM\\DataBase\\Models\\EmailModel' => $baseDir . '/app/Database/models/MessageModel.php',
    'Mint\\MRM\\DataBase\\Models\\FieldGroup' => $baseDir . '/app/Database/models/FieldGroup.php',
    'Mint\\MRM\\DataBase\\Models\\FormModel' => $baseDir . '/app/Database/models/FormModel.php',
    'Mint\\MRM\\DataBase\\Models\\NoteModel' => $baseDir . '/app/Database/models/NoteModel.php',
    'Mint\\MRM\\DataBase\\Models\\WPModel' => $baseDir . '/app/Database/models/WPModel.php',
    'Mint\\MRM\\DataBase\\Tables\\AutomationJobSchema' => $baseDir . '/app/Database/Schemas/Automation/AutomationJobs.php',
    'Mint\\MRM\\DataBase\\Tables\\AutomationLogSchema' => $baseDir . '/app/Database/Schemas/Automation/AutomationLog.php',
    'Mint\\MRM\\DataBase\\Tables\\AutomationMetaSchema' => $baseDir . '/app/Database/Schemas/Automation/AutomationMeta.php',
    'Mint\\MRM\\DataBase\\Tables\\AutomationSchema' => $baseDir . '/app/Database/Schemas/Automation/Automation.php',
    'Mint\\MRM\\DataBase\\Tables\\AutomationStepMetaSchema' => $baseDir . '/app/Database/Schemas/Automation/AutomationStepMeta.php',
    'Mint\\MRM\\DataBase\\Tables\\AutomationStepSchema' => $baseDir . '/app/Database/Schemas/Automation/AutomationSteps.php',
    'Mint\\MRM\\DataBase\\Tables\\CampaignEmailBuilderSchema' => $baseDir . '/app/Database/Schemas/CampaignEmailBuilderSchema.php',
    'Mint\\MRM\\DataBase\\Tables\\CampaignSchema' => $baseDir . '/app/Database/Schemas/CampaignSchema.php',
    'Mint\\MRM\\DataBase\\Tables\\ContactGroupPivotSchema' => $baseDir . '/app/Database/Schemas/ContactGroupPivot.php',
    'Mint\\MRM\\DataBase\\Tables\\ContactGroupSchema' => $baseDir . '/app/Database/Schemas/ContactGroup.php',
    'Mint\\MRM\\DataBase\\Tables\\ContactMetaSchema' => $baseDir . '/app/Database/Schemas/ContactMeta.php',
    'Mint\\MRM\\DataBase\\Tables\\ContactNoteSchema' => $baseDir . '/app/Database/Schemas/ContactNote.php',
    'Mint\\MRM\\DataBase\\Tables\\ContactSchema' => $baseDir . '/app/Database/Schemas/Contact.php',
    'Mint\\MRM\\DataBase\\Tables\\CustomFieldGroupSchema' => $baseDir . '/app/Database/Schemas/CustomFieldGroup.php',
    'Mint\\MRM\\DataBase\\Tables\\CustomFieldSchema' => $baseDir . '/app/Database/Schemas/CustomField.php',
    'Mint\\MRM\\DataBase\\Tables\\EmailMetaSchema' => $baseDir . '/app/Database/Schemas/MessageMeta.php',
    'Mint\\MRM\\DataBase\\Tables\\EmailSchema' => $baseDir . '/app/Database/Schemas/Message.php',
    'Mint\\MRM\\DataBase\\Tables\\FormMetaSchema' => $baseDir . '/app/Database/Schemas/FormMeta.php',
    'Mint\\MRM\\DataBase\\Tables\\FormSchema' => $baseDir . '/app/Database/Schemas/Form.php',
    'Mint\\MRM\\DataBase\\Tables\\InteractionSchema' => $baseDir . '/app/Database/Schemas/Interaction.php',
    'Mint\\MRM\\DataBase\\Upgrade' => $baseDir . '/app/Database/Upgrade.php',
    'Mint\\MRM\\DataStores\\Campaign' => $baseDir . '/app/DataStores/Campaign.php',
    'Mint\\MRM\\DataStores\\ContactData' => $baseDir . '/app/DataStores/ContactData.php',
    'Mint\\MRM\\DataStores\\CustomFieldData' => $baseDir . '/app/DataStores/CustomFieldData.php',
    'Mint\\MRM\\DataStores\\DataStoreInterface' => $baseDir . '/app/Interfaces/DataStoreInterface.php',
    'Mint\\MRM\\DataStores\\FieldGroup' => $baseDir . '/app/DataStores/FieldGroup.php',
    'Mint\\MRM\\DataStores\\FormData' => $baseDir . '/app/DataStores/FormData.php',
    'Mint\\MRM\\DataStores\\ListData' => $baseDir . '/app/DataStores/ListData.php',
    'Mint\\MRM\\DataStores\\MessageData' => $baseDir . '/app/DataStores/MessageData.php',
    'Mint\\MRM\\DataStores\\SegmentData' => $baseDir . '/app/DataStores/SegmentData.php',
    'Mint\\MRM\\DataStores\\TagData' => $baseDir . '/app/DataStores/TagData.php',
    'Mint\\MRM\\DataStores\\WordkflowData' => $baseDir . '/app/DataStores/WordkflowData.php',
    'Mint\\MRM\\Frontend\\API\\Controllers\\CookieController' => $baseDir . '/app/API/Controllers/Frontend/CookieController.php',
    'Mint\\MRM\\Frontend\\API\\Controllers\\FormSubmissionController' => $baseDir . '/app/API/Controllers/Frontend/FormSubmissionController.php',
    'Mint\\MRM\\Frontend\\API\\Controllers\\FrontendBaseController' => $baseDir . '/app/API/Controllers/Frontend/FrontendBaseController.php',
    'Mint\\MRM\\Frontend\\API\\Controllers\\PreferenceController' => $baseDir . '/app/API/Controllers/Frontend/PreferenceController.php',
    'Mint\\MRM\\Frontend\\API\\Routes\\CookieRoute' => $baseDir . '/app/API/Routes/Frontend/CookieRoute.php',
    'Mint\\MRM\\Frontend\\API\\Routes\\FormRoute' => $baseDir . '/app/API/Routes/Frontend/FormRoute.php',
    'Mint\\MRM\\Frontend\\API\\Routes\\FrontendRoute' => $baseDir . '/app/API/Routes/Frontend/FrontendRoute.php',
    'Mint\\MRM\\Frontend\\API\\Routes\\PreferenceRoute' => $baseDir . '/app/API/Routes/Frontend/PreferenceRoute.php',
    'Mint\\MRM\\Includes\\DeletePluginData' => $baseDir . '/includes/DeletePluginData.php',
    'Mint\\MRM\\Includes\\MintMailCLI' => $baseDir . '/includes/MintMailCLI.php',
    'Mint\\MRM\\Interfaces\\Schema' => $baseDir . '/app/Interfaces/Schema.php',
    'Mint\\MRM\\Internal\\Admin\\AdminAssets' => $baseDir . '/app/Internal/Admin/AdminAssets.php',
    'Mint\\MRM\\Internal\\Admin\\CreateContact' => $baseDir . '/app/Internal/Admin/Setup-Wizard/CreateContact.php',
    'Mint\\MRM\\Internal\\Admin\\EmailTemplates\\DefaultEmailTemplates' => $baseDir . '/app/Internal/Admin/Email-Templates/DefaultEmailTemplates.php',
    'Mint\\MRM\\Internal\\Admin\\FrontendAssets' => $baseDir . '/app/Internal/Frontend/FrontendAssets.php',
    'Mint\\MRM\\Internal\\Admin\\HandleFrontendMenu' => $baseDir . '/app/Internal/Frontend/HandleFrontendMenu.php',
    'Mint\\MRM\\Internal\\Admin\\MRMSecurity' => $baseDir . '/app/Internal/Admin/Security/MRMSecurity.php',
    'Mint\\MRM\\Internal\\Admin\\Notices' => $baseDir . '/app/Internal/Admin/Notices/Notices.php',
    'Mint\\MRM\\Internal\\Admin\\Notices\\DBUpgradeNotice' => $baseDir . '/app/Internal/Admin/Notices/DBUpgradeNotice.php',
    'Mint\\MRM\\Internal\\Admin\\Page\\HomeScreen' => $baseDir . '/app/Internal/Admin/Page/HomeScreen.php',
    'Mint\\MRM\\Internal\\Admin\\Page\\PageController' => $baseDir . '/app/Internal/Admin/Page/PageController.php',
    'Mint\\MRM\\Internal\\Admin\\SetupWizard' => $baseDir . '/app/Internal/Admin/Setup-Wizard/SetupWizard.php',
    'Mint\\MRM\\Internal\\Admin\\SpecialOccasionBanner' => $baseDir . '/app/Internal/Admin/SpecialOccasionBanner.php',
    'Mint\\MRM\\Internal\\Admin\\UserAssignContact' => $baseDir . '/app/Internal/Frontend/UserAssignContact.php',
    'Mint\\MRM\\Internal\\Admin\\WPUserDelete' => $baseDir . '/app/Internal/Admin/WP-User/WPUserDelete.php',
    'Mint\\MRM\\Internal\\Admin\\WooCommerceOrderDetails' => $baseDir . '/app/Internal/Admin/WooCommerce-Order/WooCommerceOrderDetails.php',
    'Mint\\MRM\\Internal\\Constants' => $baseDir . '/app/Internal/Constants.php',
    'Mint\\MRM\\Internal\\Cron\\CampaignsBackgroundProcess' => $baseDir . '/app/Internal/Cron/CampaignsBackgroundProcess.php',
    'Mint\\MRM\\Internal\\FormBuilder\\FormBuilderHelper' => $baseDir . '/app/Internal/FormBuilder/FormBuilderHelper.php',
    'Mint\\MRM\\Internal\\Frontend\\WooCommerceCheckoutContact' => $baseDir . '/app/Internal/Frontend/WooCommerceCheckoutContact.php',
    'Mint\\MRM\\Internal\\Optin\\OptinConfirmation' => $baseDir . '/app/Internal/Optin/OptinConfirmation.php',
    'Mint\\MRM\\Internal\\Optin\\UnsubscribeConfirmation' => $baseDir . '/app/Internal/Optin/UnsubscribeConfirmation.php',
    'Mint\\MRM\\Internal\\Parser\\MergeTagParser' => $baseDir . '/app/Internal/Parser/MergeTagParser.php',
    'Mint\\MRM\\Internal\\Parser\\Parser' => $baseDir . '/app/Internal/Parser/Parser.php',
    'Mint\\MRM\\Internal\\ShortCode\\ContactForm' => $baseDir . '/app/Internal/Shortcodes/ContactForm.php',
    'Mint\\MRM\\Internal\\ShortCode\\FormPreview' => $baseDir . '/app/Internal/Shortcodes/FormPreview.php',
    'Mint\\MRM\\Internal\\ShortCode\\OptinConfirmation' => $baseDir . '/app/Internal/Shortcodes/OptinConfirmation.php',
    'Mint\\MRM\\Internal\\ShortCode\\PreferencePage' => $baseDir . '/app/Internal/Shortcodes/PreferencePage.php',
    'Mint\\MRM\\Internal\\ShortCode\\ShortCode' => $baseDir . '/app/Internal/Shortcodes/Shortcode.php',
    'Mint\\MRM\\Internal\\ShortCode\\UnsubscribeConfirmation' => $baseDir . '/app/Internal/Shortcodes/UnsubscribeConfirmation.php',
    'Mint\\MRM\\Internal\\Templates\\TemplateHandler' => $baseDir . '/app/Internal/Templates/TemplateHandler.php',
    'Mint\\MRM\\Scheduler\\AbstractActionScheduler' => $baseDir . '/app/Scheduler/AbstractActionScheduler.php',
    'Mint\\MRM\\Utilites\\Helper\\AnimatedGif' => $baseDir . '/app/Utilities/Helper/GIFEncoder.php',
    'Mint\\MRM\\Utilites\\Helper\\Campaign' => $baseDir . '/app/Utilities/Helper/Campaign.php',
    'Mint\\MRM\\Utilites\\Helper\\Contact' => $baseDir . '/app/Utilities/Helper/Contact.php',
    'Mint\\MRM\\Utilites\\Helper\\Email' => $baseDir . '/app/Utilities/Helper/Email.php',
    'Mint\\MRM\\Utilites\\Helper\\Import' => $baseDir . '/app/Utilities/Helper/Import.php',
    'Mint\\MRM\\Utilities\\Helper\\TranslationString\\TransStrings' => $baseDir . '/app/Utilities/TranslationString/TransStrings.php',
    'Mint\\Mrm\\Internal\\Traits\\Singleton' => $baseDir . '/app/Internal/Traits/Singleton.php',
    'Mint\\Utilities\\Arr' => $baseDir . '/app/Utilities/Arr.php',
    'Mint\\Utilities\\MacroableTrait' => $baseDir . '/app/Utilities/MacroableTrait.php',
    'MrmActivator' => $baseDir . '/includes/MrmActivator.php',
    'MrmDeactivator' => $baseDir . '/includes/MrmDeactivator.php',
    'Mrmi18n' => $baseDir . '/includes/Mrmi18n.php',
    'TemplateAction' => $baseDir . '/app/API/Actions/Admin/Email/TemplateAction.php',
    'WP_Block_Parser' => $baseDir . '/app/Internal/FormBuilder/node_modules/@wordpress/block-serialization-default-parser/class-wp-block-parser.php',
    'WP_Block_Parser_Block' => $baseDir . '/app/Internal/FormBuilder/node_modules/@wordpress/block-serialization-default-parser/class-wp-block-parser-block.php',
    'WP_Block_Parser_Frame' => $baseDir . '/app/Internal/FormBuilder/node_modules/@wordpress/block-serialization-default-parser/class-wp-block-parser-frame.php',
    'WP_Style_Engine' => $baseDir . '/app/Internal/FormBuilder/node_modules/@wordpress/style-engine/class-wp-style-engine.php',
    'WP_Style_Engine_CSS_Declarations' => $baseDir . '/app/Internal/FormBuilder/node_modules/@wordpress/style-engine/class-wp-style-engine-css-declarations.php',
    'WP_Style_Engine_CSS_Rule' => $baseDir . '/app/Internal/FormBuilder/node_modules/@wordpress/style-engine/class-wp-style-engine-css-rule.php',
    'WP_Style_Engine_CSS_Rules_Store' => $baseDir . '/app/Internal/FormBuilder/node_modules/@wordpress/style-engine/class-wp-style-engine-css-rules-store.php',
    'WP_Style_Engine_Processor' => $baseDir . '/app/Internal/FormBuilder/node_modules/@wordpress/style-engine/class-wp-style-engine-processor.php',
);