/**
 * External dependencies
 */
import classnames from 'classnames';
import PropTypes from 'prop-types';
import React from "react";
const { RawHTML } = wp.element;
const { RichText } = wp.blockEditor;


/**
 * Internal dependencies
 */
import './editor.scss';
import './style.scss';

const PricingBlock = ( {
                           attributes: {
                               titleText,
                               desText,
                               subTitleText,
                               annualPlans,
                               lifeTimePlans
                           }
                        } ) => {
    const classes = classnames(
        'pricing-block-wrapper-class frontend',
    )

    return (
        <div className="gutenberg-pricing pos-relative" id="pricing">
            <div className="pricing-section">
                <div className="overlay"></div>
                <div className="wpfunnels-container">
                    <div className="pricing-wrapper">
                        <div className="section-title text-center">
                            <h1>
                                <span>THE MOST POWERFUL DONATION PLUGIN</span>
                                Without the High Costs
                            </h1>
                            {/* {subTitleText && <h6 dangerouslySetInnerHTML={{ __html: subTitleText }} /> }
                            {titleText && <h2 dangerouslySetInnerHTML={{ __html: titleText }} /> } */}
                            {desText && <p dangerouslySetInnerHTML={{ __html: desText }} /> }
                        </div>
                        <div className="wpfunnel-switcher">
                            <span className="annual active">Annual Plan</span>
                            <input className="switch-input" id="price-switcher" type="checkbox"/>
                            <label htmlFor="price-switcher"></label>
                            <span className="lifetime">Lifetime Plan</span>
                        </div>

                        <div id="annual-price" className="price-plan-wrapper annual-plan">
                            { annualPlans.map((item, index) => {
                                return (
                                    <div className={`single-plan ${item.featured ? 'popular' : ""}`} key={`annual-single-plan-${index}`}>

                                        <div className="tbl-header">
                                            <h3 className="plan-name" dangerouslySetInnerHTML={{ __html: item.title }} />
                                            {item.image.url &&
                                                <span className="plan-image">
                                                    <img src={item.image.url} alt="plan image"/>
                                                </span>
                                            }
                                            {item.regularPrice &&
                                                <h6 className="regular-price" dangerouslySetInnerHTML={{ __html: item.regularPrice }}></h6>
                                            }
                                            <h3 className="price">
                                                <span dangerouslySetInnerHTML={{ __html: item.price }} />
                                                <span className="plan-type" dangerouslySetInnerHTML={{ __html: item.planType }} />
                                            </h3>
                                            <p className="plan-description" dangerouslySetInnerHTML={{ __html: item.description }}/>
                                        </div>

                                        <div className="price-btn tbl-footer">
                                            <RichText.Content
                                                tagName="a"
                                                className={ `btn-default` }
                                                href= {item.button.url}
                                                value={ item.button.text }
                                            />
                                        </div>

                                        <div className="tbl-body">
                                            <ul>
                                                { item.features.map((feature, i) => {
                                                    return (
                                                        <li dangerouslySetInnerHTML={{ __html: feature.feature }} key={`annual-single-plan-features-${index}`}/>
                                                    )
                                                })}
                                            </ul>
                                        </div>
                                    </div>
                                )
                            })}
                        </div>

                        <div id="lifetime-price" className="price-plan-wrapper lifetime-plan">
                            { lifeTimePlans.map((item, index) => {
                                return (
                                    <div className={`single-plan ${item.featured ? 'popular' : ""}`} key={`lifetime-single-plan-${index}`}>
                                        <div className="tbl-header">
                                            <h3 className="plan-name" dangerouslySetInnerHTML={{ __html: item.title }} />

                                            {item.image.url &&
                                                <span className="plan-image">
                                                    <img src={item.image.url} alt="plan image"/>
                                                </span>
                                            }
                                            
                                            <h6 className="regular-price" dangerouslySetInnerHTML={{ __html: item.regularPrice }}></h6>
                                            <h3 className="price">
                                                <span dangerouslySetInnerHTML={{ __html: item.price }} />
                                                <span className="plan-type" dangerouslySetInnerHTML={{ __html: item.planType }} />
                                            </h3>
                                            <p className="plan-description" dangerouslySetInnerHTML={{ __html: item.description }}/>
                                        </div>

                                        <div className="price-btn tbl-footer">
                                            <RichText.Content
                                                tagName="a"
                                                className={ `btn-default` }
                                                href= {item.button.url}
                                                value={ item.button.text }
                                            />
                                        </div>

                                        <div className="tbl-body">
                                            <ul>
                                                { item.features.map((feature, i) => {
                                                    return (
                                                        <li dangerouslySetInnerHTML={{ __html: feature.feature }} key={`lifetime-single-plan-features-${index}`}/>
                                                    )
                                                })}
                                            </ul>
                                        </div>
                                    </div>
                                )
                            })}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};


PricingBlock.propTypes = {
    attributes: PropTypes.object.isRequired,
};

export default PricingBlock;
