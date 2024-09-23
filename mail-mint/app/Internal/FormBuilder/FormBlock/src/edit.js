import React from "react";
import PropTypes from 'prop-types';

const {
    Component,
} = wp.element;
const { compose } = wp.compose;
const {
    SelectControl,
    Panel,
    PanelBody,
} = wp.components;
const {
    InspectorControls,
} = wp.blockEditor;

/**
 * Internal dependencies
 *
 * */
import './editor.scss'

class Editor extends Component {

    static propTypes = {
        attributes      : PropTypes.object.isRequired,
        isSelected      : PropTypes.bool.isRequired,
        name            : PropTypes.string.isRequired,
        setAttributes   : PropTypes.func.isRequired,
    };
    componentDidMount() {
        wp.apiFetch( { path : 'mrm/v1/forms/form-list' } ).then( response => {
            const form_list = response.data;
            this.props.setAttributes({form_list_data: form_list});
        } );
		var form_id = this.props.attributes.form_id
		let that =this;
		if( form_id ) {
			jQuery.ajax({
				url: window.getmm_block_object.ajaxUrl,
				type: "POST",
				data: {
					action          	: 'show_form_markup',
					post_id         	: form_id,
				},
				// dataType: 'json',
				success: function (response) {
					that.props.setAttributes({ render_block : response })
					that.props.setAttributes({
						show_loader: false
					});
				}
			});
		}
    }
    onChangeAttribute = (key, value) => {
        this.props.setAttributes({
            ...this.props.attributes,
            [key]: value
        });

        this.props.setAttributes({
            form_id: value
        });

        this.props.setAttributes({
            show_loader: true
        });
        let that =this;
        jQuery.ajax({
                url: window.getmm_block_object.ajaxUrl,
                type: "POST",
                data: {
                    action          	: 'show_form_markup',
                    post_id         	: value,
                },
                // dataType: 'json',
                success: function (response) {
                    that.props.setAttributes({ render_block : response })
                    that.props.setAttributes({
                        show_loader: false
                    });
                }
            });


    }
    customFields = () =>{
        let { attributes, setAttributes } 	= this.props;

        return (
            <PanelBody title="Form List" className="inner-pannel">
                <SelectControl
                    label="Form Name"
                    value={attributes.form_id}
                    onChange={ id => this.onChangeAttribute( 'form_id', id )}
                    options={attributes.form_list_data}
                />
                <a href="admin.php?page=mrm-admin#/form-builder/">Create a new form</a>

            </PanelBody>
        )
    }
    getInspectorControls = () => {
        return (
            <InspectorControls key="mrm-mrm-form-inspector-controls">
                <div id="mrm-block-inspected-inspector-control-wrapper">
                    <Panel>
                        {this.customFields()}
                    </Panel>
                </div>
            </InspectorControls>
        );
    };
    render() {
        const {
            attributes,
            setAttributes
        } = this.props;

        const className = attributes.form_id != 0 ? 'mintmrm-sbuscription-form-block mint-form-added': "mintmrm-sbuscription-form-block";
        return (
            <>
                { this.getInspectorControls() }
                {attributes.form_list_data.length == 1 &&
                    <>
                        <div className="mintmrm">
                            <p>No form added</p>
                        </div>

                        <a href="admin.php?page=mrm-admin#/form-builder/">Create a new form</a>
                    </>
                }
                <div className="mintmrm">
                    <div className={className}>
                        {attributes.show_loader && <span className="mintmrm-loader"></span>}
                        { !attributes.show_loader && attributes.form_list_data.length != 1 &&
                            <>
                                <form action="">
                                    <div className="post__content" dangerouslySetInnerHTML={{__html: attributes.render_block}}></div>
                                </form>

                            </>

                        }
                        {attributes.form_id == '0' && attributes.form_list_data.length != 1 &&  <SelectControl
                            label="Form Name"
                            value={attributes.form_id}
                            onChange={ id => this.onChangeAttribute( 'form_id', id )}
                            options={attributes.form_list_data}
                        />}
                        {attributes.form_id == '0' && attributes.form_list_data.length != 1 &&  <a href="admin.php?page=mrm-admin#/form-builder/">Create a new form</a>}
                    </div>
                </div>



            </>


        );
    }
}

export default compose( [
] )( Editor );
