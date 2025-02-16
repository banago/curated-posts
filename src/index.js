/**
 * WordPress dependencies
 */
import domReady from '@wordpress/dom-ready';
import { createRoot, useState, useEffect } from '@wordpress/element';
import { ComboboxControl } from '@wordpress/components';

/**
 * External dependencies
 */
import {
    DndContext,
    closestCenter,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

/**
 * Internal dependencies
 */
import './style.css';
import './images/spinner.gif';

/**
 * Sortable table row component with drag and drop functionality.
 *
 * @param {Object} props           Component properties.
 * @param {number} props.id        Post ID.
 * @param {string} props.title     Post title.
 * @param {string} props.date      Formatted post date.
 * @param {Function} props.onRemove Callback to remove post from list.
 * @param {number} props.index     Current position in the list.
 * @return {JSX.Element}           Sortable table row component.
 */
const SortableItem = ({ id, title, date, onRemove, index }) => {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    const className = isDragging ? 'dragging' : '';

    return (
        <tr ref={setNodeRef} style={style} className={className} {...attributes}>
            <td className="icon drag-handle" title="Drag to reorder" {...listeners}>
                <span 
                    className="dashicons dashicons-menu post-state-format" 
                    role="button" 
                    tabIndex={0}
                    aria-label={`Drag ${title} to reorder. Current position: ${index + 1}`}
                    onKeyDown={(e) => {
                        if (e.key === ' ' || e.key === 'Enter') {
                            e.preventDefault();
                            e.currentTarget.click();
                        }
                    }}
                ></span>
            </td>
            <td>
                <input type="hidden" name="curated_posts[]" value={id} />
                {title}
            </td>
            <td>{date}</td>
            <td>
                <button 
                    type="button"
                    className="dashicons dashicons-no-alt curated-delete"
                    onClick={(e) => {
                        e.stopPropagation();
                        onRemove(id);
                    }}
                    aria-label={`Remove ${title} from list`}
                    title="Remove from list"
                    onKeyDown={(e) => {
                        if (e.key === 'Delete' || e.key === 'Backspace') {
                            e.stopPropagation();
                            onRemove(id);
                        }
                    }}
                ></button>
            </td>
        </tr>
    );
};

/**
 * Hidden form fields component for saving the curated posts data.
 *
 * @param {Object} props               Component properties.
 * @param {Array} props.selectedPosts  Array of selected posts.
 * @return {JSX.Element}              Hidden input fields.
 */
const SaveFields = ({ selectedPosts }) => {
    // Create hidden inputs with the current selected posts and nonces
    // These will be submitted with the WordPress post form
    return (
        <>
            <input 
                type="hidden" 
                name="curated_posts" 
                value={selectedPosts.map(post => post.id).join(',')} 
            />
            <input 
                type="hidden" 
                name="curated_meta_nonce" 
                value={window?.curatedPosts?.nonce || ''} 
            />
        </>
    );
};

/**
 * Main settings page component for managing curated posts.
 * Handles post search, selection, ordering, and persistence.
 *
 * @return {JSX.Element} Settings page component.
 */
const SettingsPage = () => {
    const [selectedPosts, setSelectedPosts] = useState([]);
    const [searchResults, setSearchResults] = useState([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isInitialLoading, setIsInitialLoading] = useState(true);
    const [error, setError] = useState(null);

    // Load existing curated posts on mount
    useEffect(() => {
        const loadExistingPosts = async () => {
            setError(null);
            setIsInitialLoading(true);
            
            // Get existing curated posts from the meta field
            const existingPosts = window?.curatedPosts?.posts || [];
            
            if (!existingPosts || existingPosts.length === 0) {
                setSelectedPosts([]);
                setIsInitialLoading(false);
                return;
            }

            try {
                // Fetch the posts data
                const postsResponse = await fetch(`/wp-json/curated-posts/v1/search?include=${existingPosts.join(',')}`, {
                    credentials: 'same-origin',
                    headers: {
                        'X-WP-Nonce': window?.curatedPosts?.restNonce || ''
                    }
                });
                if (!postsResponse.ok) {
                    throw new Error('Failed to load posts');
                }
                const posts = await postsResponse.json();
                
                if (!Array.isArray(posts)) {
                    throw new Error('Invalid response format');
                }

                // Map posts to match our format and maintain the original order
                const orderedPosts = existingPosts.map(id => {
                    const post = posts.find(p => p.id === parseInt(id));
                    if (!post) return null;
                    return {
                        id: post.id,
                        title: post.title.rendered,
                        date: new Date(post.date).toLocaleDateString('en-GB', {
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric'
                        })
                    };
                }).filter(Boolean);

                setSelectedPosts(orderedPosts);
            } catch (error) {
                console.error('Error loading curated posts:', error);
                setError('Failed to load curated posts. Please refresh the page to try again.');
            } finally {
                setIsInitialLoading(false);
            }
        };

        loadExistingPosts();
    }, []);

    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        })
    );

    const handleDragEnd = (event) => {
        const { active, over } = event;

        if (active.id !== over.id) {
            setSelectedPosts((posts) => {
                const oldIndex = posts.findIndex((post) => post.id === active.id);
                const newIndex = posts.findIndex((post) => post.id === over.id);
                return arrayMove(posts, oldIndex, newIndex);
            });
        }
    };

    const handleSearch = async (searchTerm) => {
        if (!searchTerm) {
            setSearchResults([]);
            return;
        }

        setIsLoading(true);
        setError(null);
        
        try {
            const response = await fetch(`/wp-json/curated-posts/v1/search?search=${encodeURIComponent(searchTerm)}`, {
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': window?.curatedPosts?.restNonce || ''
                }
            });
            
            if (!response.ok) {
                throw new Error('Failed to search posts');
            }
            
            const posts = await response.json();
            const total = parseInt(response.headers.get('X-WP-Total') || '0');
            
            const results = posts.map(post => ({
                label: post.title.rendered,
                value: post.id.toString(),
                post: post,
                description: `Published: ${new Date(post.date).toLocaleDateString('en-GB', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                })}`
            }));

            // Add a message if there are more results
            if (total > 10) {
                results.push({
                    label: `... and ${total - 10} more posts match your search`,
                    value: '',
                    disabled: true
                });
            }

            setSearchResults(results);
        } catch (error) {
            console.error('Error searching posts:', error);
            setError('Failed to search posts. Please try again.');
            setSearchResults([]);
        } finally {
            setIsLoading(false);
        }
    };

    const handleSelect = (postId) => {
        if (!postId) return;

        const selectedResult = searchResults.find(result => result.value === postId);
        if (selectedResult && !selectedPosts.find(p => p.id === selectedResult.post.id)) {
            setSelectedPosts([...selectedPosts, {
                id: selectedResult.post.id,
                title: selectedResult.post.title.rendered,
                date: new Date(selectedResult.post.date).toLocaleDateString('en-GB', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                })
            }]);
        }
    };

    const handleRemovePost = (postId) => {
        setSelectedPosts(selectedPosts.filter(post => post.id !== postId));
    };

    return (
        <div className="cp-wrap">
            {error && (
                <div className="notice notice-error">
                    <p>{error}</p>
                </div>
            )}

            {isInitialLoading ? (
                <div className="loading-spinner">Loading...</div>
            ) : (
                <>
                    <ComboboxControl
                label="Select posts to add to curated list below:"
                value=""
                onChange={handleSelect}
                onFilterValueChange={handleSearch}
                options={searchResults}
                isLoading={isLoading}
            />

            <table className="widefat curated-posts-table">
                <thead>
                    <tr>
                        <th style={{ width: '1px' }}>&nbsp;</th>
                        <th>Title</th>
                        <th style={{ width: '20%' }}>Published</th>
                        <th style={{ width: '1px' }}>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                    {selectedPosts.length === 0 ? (
                        <tr className="curated-placeholder">
                            <td colSpan="4">
                                No posts found. Use the menu above to add posts to this list.
                            </td>
                        </tr>
                    ) : (
                        <DndContext
                            sensors={sensors}
                            collisionDetection={closestCenter}
                            onDragEnd={handleDragEnd}
                        >
                            <SortableContext
                                items={selectedPosts.map(post => post.id)}
                                strategy={verticalListSortingStrategy}
                            >
                                {selectedPosts.map((post, index) => (
                                    <SortableItem
                                        key={post.id}
                                        id={post.id}
                                        title={post.title}
                                        date={post.date}
                                        onRemove={handleRemovePost}
                                        index={index}
                                    />
                                ))}
                            </SortableContext>
                        </DndContext>
                    )}
                </tbody>
            </table>
            <p style={{ overflow: 'hidden' }}>
                <span className="howto alignleft">
                    Drag and drop to reorder posts.
                </span>
                <span className="credits alignright">
                    Made with <i aria-label="love" className="heart">❤</i> by{' '}
                    <a href="https://github.com/banago" target="_blank">@banago</a>
                </span>
            </p>
            <SaveFields selectedPosts={selectedPosts} />
                </>
            )}
        </div>
    );
};

domReady(() => {
    const root = createRoot(
        document.getElementById('curated-posts-settings')
    );

    root.render(<SettingsPage />); 
});
